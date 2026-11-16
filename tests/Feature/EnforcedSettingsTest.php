<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\AppSetting;
use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengaturan yang sebelumnya hanya tersimpan tanpa efek sekarang benar-benar
 * dipakai: prefix nomor, jatuh tempo, batas nominal kategori, dan batas minimal
 * pembayaran.
 */
class EnforcedSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private PaymentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $unit = Unit::query()->firstOrFail();

        $this->student = Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => 'SET-001',
            'name' => 'Siswa Uji Pengaturan',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->category = PaymentCategory::query()->create([
            'code' => 'SPP-SET',
            'name' => 'SPP Uji',
            'type' => 'monthly',
            'default_amount' => 150000,
            'due_day' => 25,
            'is_active' => true,
        ]);
    }

    public function test_bill_number_uses_configured_bill_prefix(): void
    {
        AppSetting::set('bill_prefix', 'TGH');

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'period' => '2026-11',
            'academic_year_id' => $this->academicYearId(),
        ]);

        $this->assertStringStartsWith('TGH-', $bill->bill_number);
    }

    public function test_bill_due_day_falls_back_to_system_setting(): void
    {
        AppSetting::set('bill_due_day', 5);
        $this->category->update(['due_day' => 0]);

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'period' => '2026-11',
            'academic_year_id' => $this->academicYearId(),
        ]);

        $this->assertSame('2026-11-05', $bill->due_date->format('Y-m-d'));
    }

    public function test_category_default_amount_must_respect_system_bounds(): void
    {
        AppSetting::set('min_category_amount', 50000);
        AppSetting::set('max_category_amount', 100000);

        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test('master.payment-category-index')
            ->set('code', 'SPP-OOB')
            ->set('name', 'SPP Di Luar Batas')
            ->set('type', 'monthly')
            ->set('default_amount', '250000')
            ->set('due_day', 10)
            ->set('sort_order', 1)
            ->set('is_active', true)
            ->call('save')
            ->assertHasErrors(['default_amount']);
    }

    public function test_payment_amount_below_system_minimum_is_rejected(): void
    {
        AppSetting::set('min_payment_amount', 50000);

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'period' => '2026-11',
            'academic_year_id' => $this->academicYearId(),
        ]);

        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);
        $methodId = \App\Models\PaymentMethod::query()->value('id');

        Livewire::actingAs($user)
            ->test('payments.payment-create')
            ->set('student_id', $this->student->id)
            ->set('payment_method_id', $methodId)
            ->set('allocations', [$bill->id => '10000'])
            ->call('save')
            ->assertHasErrors(['amount']);
    }

    public function test_payment_number_uses_configured_payment_prefix(): void
    {
        AppSetting::set('payment_prefix', 'BYR');

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'period' => '2026-11',
            'academic_year_id' => $this->academicYearId(),
        ]);

        $payment = app(PaymentService::class)->create(
            [
                'student_id' => $this->student->id,
                'amount' => 150000,
                'payment_method_id' => \App\Models\PaymentMethod::query()->value('id'),
            ],
            [['student_bill_id' => $bill->id, 'amount' => 150000]],
        );

        $this->assertStringStartsWith('BYR-', $payment->transaction_number);
    }

    private function academicYearId(): int
    {
        return (int) \App\Models\AcademicYear::query()->value('id');
    }
}

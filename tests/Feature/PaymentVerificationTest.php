<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentCategoryType;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Livewire\Payments\PaymentVerification;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return array{0: Payment, 1: StudentBill} */
    private function makePendingPayment(string $number = 'VER-1', float $amount = 50000): array
    {
        $unit = Unit::query()->create(['code' => 'TV', 'name' => 'TV']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create(['unit_id' => $unit->id, 'academic_year_id' => $year->id, 'name' => 'XV']);
        $category = PaymentCategory::query()->create([
            'code' => 'PV', 'name' => 'PV', 'type' => PaymentCategoryType::Monthly,
            'default_amount' => 100000, 'due_day' => 10, 'is_active' => true,
        ]);
        $student = Student::query()->create([
            'unit_id' => $unit->id, 'school_class_id' => $class->id,
            'nis' => '9', 'nisn' => '9', 'name' => 'Siswa Verifikasi',
            'gender' => \App\Enums\Gender::Male, 'status' => StudentStatus::Active, 'entry_year' => 2026,
        ]);
        $bill = app(BillingService::class)->create([
            'student_id' => $student->id, 'payment_category_id' => $category->id,
            'academic_year_id' => $year->id, 'period' => '2026-12', 'amount' => 100000,
        ]);
        $payment = app(PaymentService::class)->create(
            ['student_id' => $student->id, 'transaction_number' => $number, 'amount' => $amount],
            [['student_bill_id' => $bill->id, 'amount' => $amount]],
        );

        return [$payment, $bill];
    }

    private function userWith(string $permission): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'verifier@example.test'],
            ['name' => 'Verifier', 'password' => bcrypt('secret'), 'status' => \App\Enums\UserStatus::Active],
        );
        $user->givePermissionTo($permission);

        return $user;
    }

    public function test_verification_page_lists_pending_payments(): void
    {
        [$payment] = $this->makePendingPayment();

        Livewire::actingAs($this->userWith('payments.verify'))
            ->test(PaymentVerification::class)
            ->assertOk()
            ->assertSee($payment->transaction_number)
            ->assertSee('Siswa Verifikasi');
    }

    public function test_verification_page_requires_verify_permission(): void
    {
        $this->actingAs($this->userWith('payments.view'))
            ->get(route('payments.verification'))
            ->assertStatus(403);
    }

    public function test_approve_marks_payment_approved_and_updates_bill(): void
    {
        [$payment, $bill] = $this->makePendingPayment();

        Livewire::actingAs($this->userWith('payments.verify'))
            ->test(PaymentVerification::class)
            ->call('approve', $payment->id)
            ->assertHasNoErrors();

        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertEquals(50000.0, (float) $bill->fresh()->paid_amount);
        $this->assertEquals(50000.0, (float) $bill->fresh()->remaining_amount);
        $this->assertDatabaseHas('payment_verifications', ['payment_id' => $payment->id, 'status' => 'approved']);
    }

    public function test_reject_requires_reason_and_keeps_bill_balance(): void
    {
        [$payment, $bill] = $this->makePendingPayment();

        Livewire::actingAs($this->userWith('payments.verify'))
            ->test(PaymentVerification::class)
            ->call('startReject', $payment->id)
            ->set('reason', '')
            ->call('reject')
            ->assertHasErrors(['reason' => 'required']);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);

        Livewire::actingAs($this->userWith('payments.verify'))
            ->test(PaymentVerification::class)
            ->call('startReject', $payment->id)
            ->set('reason', 'Bukti tidak sesuai')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame(PaymentStatus::Rejected, $payment->fresh()->status);
        $this->assertEquals(0.0, (float) $bill->fresh()->paid_amount);
        $this->assertDatabaseHas('payment_verifications', ['payment_id' => $payment->id, 'status' => 'rejected']);
    }

    public function test_payment_proof_requires_verify_permission_to_process(): void
    {
        [$payment] = $this->makePendingPayment();

        $this->actingAs($this->userWith('payments.view'))
            ->get(route('payments.verification'))
            ->assertForbidden();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_verification_component_render_requires_verify_permission(): void
    {
        $this->makePendingPayment();

        Livewire::actingAs($this->userWith('payments.view'))
            ->test(PaymentVerification::class)
            ->assertForbidden();
    }
}

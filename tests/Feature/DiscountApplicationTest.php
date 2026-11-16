<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Livewire\Bills\DiscountIndex;
use App\Models\AcademicYear;
use App\Models\AppSetting;
use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class DiscountApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private AcademicYear $year;

    private PaymentCategory $category;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->unit = Unit::query()->firstOrFail();
        $this->year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $this->category = PaymentCategory::query()->create([
            'code' => 'SPP-DISC',
            'name' => 'SPP Potongan',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 400000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $this->student = Student::query()->create([
            'unit_id' => $this->unit->id,
            'nis' => 'DISC-001',
            'name' => 'Siswa Beasiswa',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    public function test_percentage_discount_is_applied_automatically(): void
    {
        StudentDiscount::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => null,
            'type' => DiscountService::TYPE_PERCENT,
            'value' => 25,
            'reason' => 'Beasiswa prestasi',
            'is_active' => true,
        ]);

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
        ]);

        $this->assertSame('400000.00', $bill->original_amount);
        $this->assertSame('100000.00', $bill->discount_amount);
        $this->assertSame('300000.00', $bill->final_amount);

        $this->assertDatabaseHas('bill_discounts', [
            'student_bill_id' => $bill->id,
            'type' => DiscountService::TYPE_PERCENT,
            'amount' => 100000,
            'reason' => 'Beasiswa prestasi',
        ]);
    }

    public function test_fixed_discount_only_applies_to_matching_category(): void
    {
        $otherCategory = PaymentCategory::query()->create([
            'code' => 'GEDUNG-DISC',
            'name' => 'Gedung',
            'type' => PaymentCategoryType::Once,
            'default_amount' => 500000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        StudentDiscount::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $otherCategory->id,
            'type' => DiscountService::TYPE_FIXED,
            'value' => 50000,
            'is_active' => true,
        ]);

        $billing = app(BillingService::class);

        $spp = $billing->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
        ]);

        $gedung = $billing->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $otherCategory->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
        ]);

        $this->assertSame('0.00', $spp->discount_amount);
        $this->assertSame('50000.00', $gedung->discount_amount);
    }

    public function test_inactive_discount_is_ignored(): void
    {
        StudentDiscount::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => null,
            'type' => DiscountService::TYPE_PERCENT,
            'value' => 50,
            'is_active' => false,
        ]);

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
        ]);

        $this->assertSame('0.00', $bill->discount_amount);
    }

    public function test_multiple_discounts_are_summed_but_capped_at_amount(): void
    {
        StudentDiscount::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => null,
            'type' => DiscountService::TYPE_PERCENT,
            'value' => 100,
            'is_active' => true,
        ]);

        StudentDiscount::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'type' => DiscountService::TYPE_FIXED,
            'value' => 150000,
            'is_active' => true,
        ]);

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
        ]);

        $this->assertSame('400000.00', $bill->discount_amount);
        $this->assertSame('0.00', $bill->final_amount);
    }

    public function test_manual_discount_overrides_automatic_rules(): void
    {
        StudentDiscount::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => null,
            'type' => DiscountService::TYPE_PERCENT,
            'value' => 25,
            'is_active' => true,
        ]);

        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
            'discount_amount' => 10000,
        ]);

        $this->assertSame('10000.00', $bill->discount_amount);
    }

    public function test_bill_items_are_stored_when_total_matches_amount(): void
    {
        $bill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
            'items' => [
                ['description' => 'SPP Pokok', 'amount' => 300000],
                ['description' => 'Kegiatan', 'amount' => 100000],
            ],
        ]);

        $this->assertDatabaseCount('bill_items', 2);
        $this->assertDatabaseHas('bill_items', [
            'bill_id' => $bill->bill_id,
            'description' => 'SPP Pokok',
            'amount' => 300000,
            'sort_order' => 0,
        ]);
    }

    public function test_bill_items_total_must_match_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
            'items' => [
                ['description' => 'SPP Pokok', 'amount' => 100000],
            ],
        ]);
    }

    public function test_bill_items_are_limited_by_system_setting(): void
    {
        AppSetting::set('max_bill_items', 2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('melebihi batas 2 item');

        app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
            'items' => [
                ['description' => 'A', 'amount' => 100000],
                ['description' => 'B', 'amount' => 100000],
                ['description' => 'C', 'amount' => 200000],
            ],
        ]);
    }

    public function test_discount_page_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('discounts.index'))->assertForbidden();
    }

    public function test_discount_crud_works_through_livewire(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(DiscountIndex::class)
            ->call('create')
            ->set('student_id', $this->student->id)
            ->set('payment_category_id', $this->category->id)
            ->set('type', DiscountService::TYPE_PERCENT)
            ->set('value', '10')
            ->set('reason', 'Keringanan')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('student_discounts', [
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'type' => DiscountService::TYPE_PERCENT,
            'value' => 10,
        ]);

        $discount = StudentDiscount::query()->firstOrFail();

        Livewire::actingAs($this->superadmin())
            ->test(DiscountIndex::class)
            ->call('toggleActive', $discount->id)
            ->assertHasNoErrors();

        $this->assertFalse($discount->fresh()->is_active);
    }

    public function test_percentage_discount_cannot_exceed_one_hundred(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(DiscountIndex::class)
            ->call('create')
            ->set('student_id', $this->student->id)
            ->set('type', DiscountService::TYPE_PERCENT)
            ->set('value', '150')
            ->set('is_active', true)
            ->call('save')
            ->assertHasErrors(['value']);
    }

    public function test_duplicate_discount_rule_is_rejected(): void
    {
        StudentDiscount::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'type' => DiscountService::TYPE_PERCENT,
            'value' => 10,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(DiscountIndex::class)
            ->call('create')
            ->set('student_id', $this->student->id)
            ->set('payment_category_id', $this->category->id)
            ->set('type', DiscountService::TYPE_PERCENT)
            ->set('value', '20')
            ->set('is_active', true)
            ->call('save')
            ->assertHasErrors(['type']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Livewire\Bills\BillGenerate;
use App\Livewire\Bills\BillIndex;
use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingGenerateModesTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private SchoolClass $class;

    private PaymentCategory $category;

    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->unit = Unit::query()->firstOrFail();
        $this->year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $this->class = SchoolClass::query()->create([
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->year->id,
            'name' => 'X-MODE',
        ]);

        $this->category = PaymentCategory::query()->create([
            'code' => 'SPP-MODE',
            'name' => 'SPP Mode',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 200000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        foreach (range(1, 2) as $index) {
            Student::query()->create([
                'unit_id' => $this->unit->id,
                'school_class_id' => $this->class->id,
                'nis' => 'MODE-'.$index,
                'name' => 'Siswa Mode '.$index,
                'gender' => Gender::Male,
                'status' => StudentStatus::Active,
            ]);
        }
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    public function test_individual_mode_creates_single_bill_for_selected_student(): void
    {
        $student = Student::query()->orderBy('id')->firstOrFail();

        Livewire::actingAs($this->superadmin())
            ->test(BillGenerate::class)
            ->set('mode', 'individu')
            ->set('academic_year_id', $this->year->id)
            ->set('payment_category_id', $this->category->id)
            ->set('student_id', $student->id)
            ->set('period', '2026-11')
            ->set('due_date', '2026-11-10')
            ->set('amount', '150000')
            ->set('discount_amount', '25000')
            ->set('confirmed', true)
            ->call('generate')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('student_bills', 1);
        $this->assertDatabaseHas('student_bills', [
            'student_id' => $student->id,
            'original_amount' => 150000,
            'discount_amount' => 25000,
            'final_amount' => 125000,
        ]);
    }

    public function test_individual_mode_rejects_discount_above_amount(): void
    {
        $student = Student::query()->orderBy('id')->firstOrFail();

        Livewire::actingAs($this->superadmin())
            ->test(BillGenerate::class)
            ->set('mode', 'individu')
            ->set('academic_year_id', $this->year->id)
            ->set('payment_category_id', $this->category->id)
            ->set('student_id', $student->id)
            ->set('period', '2026-11')
            ->set('due_date', '2026-11-10')
            ->set('amount', '100000')
            ->set('discount_amount', '150000')
            ->set('confirmed', true)
            ->call('generate')
            ->assertHasErrors(['discount_amount']);

        $this->assertDatabaseCount('student_bills', 0);
    }

    public function test_monthly_mode_creates_bills_for_each_month_in_range(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(BillGenerate::class)
            ->set('mode', 'bulanan')
            ->set('academic_year_id', $this->year->id)
            ->set('payment_category_id', $this->category->id)
            ->set('unit_id', $this->unit->id)
            ->set('class_id', $this->class->id)
            ->set('period', '2026-01')
            ->set('period_end', '2026-03')
            ->set('due_date', '2026-01-10')
            ->set('confirmed', true)
            ->call('generate')
            ->assertHasNoErrors();

        // 2 siswa x 3 bulan.
        $this->assertDatabaseCount('student_bills', 6);
        $this->assertDatabaseHas('bills', ['period' => '2026-01']);
        $this->assertDatabaseHas('bills', ['period' => '2026-02']);
        $this->assertDatabaseHas('bills', ['period' => '2026-03']);
    }

    public function test_monthly_mode_rejects_reversed_range(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(BillGenerate::class)
            ->set('mode', 'bulanan')
            ->set('academic_year_id', $this->year->id)
            ->set('payment_category_id', $this->category->id)
            ->set('period', '2026-05')
            ->set('period_end', '2026-02')
            ->set('due_date', '2026-05-10')
            ->set('confirmed', true)
            ->call('generate')
            ->assertHasErrors(['period_end']);
    }

    public function test_bill_index_filters_by_unit_and_class(): void
    {
        $otherUnit = Unit::query()->whereKeyNot($this->unit->id)->firstOrFail();
        $otherClass = SchoolClass::query()->create([
            'unit_id' => $otherUnit->id,
            'academic_year_id' => $this->year->id,
            'name' => 'Y-LAIN',
        ]);

        $other = Student::query()->create([
            'unit_id' => $otherUnit->id,
            'school_class_id' => $otherClass->id,
            'nis' => 'MODE-LAIN',
            'name' => 'Siswa Unit Lain',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);

        $billing = app(\App\Services\BillingService::class);

        foreach (Student::query()->get() as $student) {
            $billing->create([
                'student_id' => $student->id,
                'payment_category_id' => $this->category->id,
                'academic_year_id' => $this->year->id,
                'period' => '2026-11',
            ]);
        }

        $component = Livewire::actingAs($this->superadmin())->test(BillIndex::class);
        $component->assertViewHas('bills', fn ($bills) => $bills->total() === 3);

        $component->set('unitFilter', (string) $otherUnit->id)
            ->assertViewHas('bills', fn ($bills) => $bills->total() === 1);

        $component->set('classFilter', (string) $otherClass->id)
            ->assertViewHas('bills', fn ($bills) => $bills->total() === 1);

        $component->set('unitFilter', (string) $this->unit->id)
            ->assertViewHas('bills', fn ($bills) => $bills->total() === 2);

        $this->assertSame($otherUnit->id, $other->unit_id);
    }
}

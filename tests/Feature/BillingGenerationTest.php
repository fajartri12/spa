<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\GenerateIndividualBillAction;
use App\Actions\GenerateMassBillAction;
use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use App\Livewire\Bills\BillGenerate;
use Tests\TestCase;

class BillingGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function billingData(): array
    {
        $unit = Unit::query()->create(['code' => 'TEST', 'name' => 'Unit Test']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create(['unit_id' => $unit->id, 'academic_year_id' => $year->id, 'name' => 'X-TEST']);
        $category = PaymentCategory::query()->create([
            'code' => 'SPP-TEST', 'name' => 'SPP Test', 'type' => PaymentCategoryType::Monthly,
            'default_amount' => 350000, 'due_day' => 10, 'is_active' => true,
        ]);

        foreach (['Siswa Satu', 'Siswa Dua'] as $index => $name) {
            Student::query()->create([
                'unit_id' => $unit->id, 'school_class_id' => $class->id,
                'nis' => 'TEST-'.($index + 1), 'nisn' => 'NISN-'.($index + 1), 'name' => $name,
                'gender' => Gender::Male, 'status' => StudentStatus::Active, 'entry_year' => 2026,
            ]);
        }

        return [$unit, $year, $category];
    }

    public function test_mass_generation_creates_one_bill_for_each_active_student_in_unit(): void
    {
        [$unit, $year, $category] = $this->billingData();

        $bills = app(GenerateMassBillAction::class)->execute([
            'unit_id' => $unit->id, 'academic_year_id' => $year->id,
            'payment_category_id' => $category->id, 'period' => '2026-11',
        ]);

        $this->assertCount(2, $bills);
        $this->assertDatabaseCount('student_bills', 2);
        $this->assertDatabaseCount('bills', 2);
    }

    public function test_duplicate_generation_is_rejected_without_creating_extra_bills(): void
    {
        [$unit, $year, $category] = $this->billingData();
        $attributes = ['unit_id' => $unit->id, 'academic_year_id' => $year->id, 'payment_category_id' => $category->id, 'period' => '2026-11'];
        app(GenerateMassBillAction::class)->execute($attributes);

        $this->expectException(InvalidArgumentException::class);
        app(GenerateMassBillAction::class)->execute($attributes);
    }

    public function test_discount_and_remaining_amount_are_calculated(): void
    {
        [, $year, $category] = $this->billingData();
        $student = Student::query()->firstOrFail();

        $bill = app(GenerateIndividualBillAction::class)->execute([
            'student_id' => $student->id, 'academic_year_id' => $year->id,
            'payment_category_id' => $category->id, 'period' => '2026-12',
            'amount' => 350000, 'discount_amount' => 50000,
        ]);

        $this->assertSame('350000.00', $bill->original_amount);
        $this->assertSame('50000.00', $bill->discount_amount);
        $this->assertSame('300000.00', $bill->final_amount);
        $this->assertSame('300000.00', $bill->remaining_amount);
    }

    public function test_user_without_generate_permission_cannot_access_generation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('bills.generate'))->assertForbidden();
        Livewire::actingAs($user)->test(BillGenerate::class)->assertForbidden();
    }
}

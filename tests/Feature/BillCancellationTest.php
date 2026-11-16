<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillStatus;
use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillCancellationTest extends TestCase
{
    use RefreshDatabase;

    private StudentBill $studentBill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $unit = Unit::query()->create(['code' => 'CAN', 'name' => 'Cancel Unit']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create(['unit_id' => $unit->id, 'academic_year_id' => $year->id, 'name' => 'X-CAN']);
        $category = PaymentCategory::query()->create([
            'code' => 'CAN', 'name' => 'Cancel', 'type' => PaymentCategoryType::Monthly,
            'default_amount' => 200000, 'due_day' => 10, 'is_active' => true,
        ]);
        $student = Student::query()->create([
            'unit_id' => $unit->id, 'school_class_id' => $class->id,
            'nis' => 'CAN-1', 'nisn' => 'CAN-1', 'name' => 'Siswa Cancel',
            'gender' => Gender::Male, 'status' => StudentStatus::Active, 'entry_year' => 2026,
        ]);
        $this->studentBill = app(BillingService::class)->create([
            'student_id' => $student->id, 'payment_category_id' => $category->id,
            'academic_year_id' => $year->id, 'period' => '2026-11', 'amount' => 200000,
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    public function test_unpaid_bill_can_be_cancelled(): void
    {
        app(BillingService::class)->cancel($this->studentBill);

        $this->assertDatabaseHas('student_bills', ['id' => $this->studentBill->id, 'status' => BillStatus::Cancelled->value]);
    }

    public function test_bill_with_approved_payment_cannot_be_cancelled(): void
    {
        $payment = app(PaymentService::class)->create(
            ['student_id' => $this->studentBill->student_id, 'transaction_number' => 'CAN-1', 'amount' => 50000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 50000]],
        );
        app(PaymentService::class)->approve($payment);

        $this->expectException(InvalidArgumentException::class);
        app(BillingService::class)->cancel($this->studentBill);
    }

    public function test_bill_with_pending_payment_cannot_be_cancelled(): void
    {
        app(PaymentService::class)->create(
            ['student_id' => $this->studentBill->student_id, 'transaction_number' => 'CAN-2', 'amount' => 50000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 50000]],
        );

        $this->expectException(InvalidArgumentException::class);
        app(BillingService::class)->cancel($this->studentBill);
    }

    public function test_cancelled_bill_cannot_be_cancelled_twice(): void
    {
        app(BillingService::class)->cancel($this->studentBill);

        $this->expectException(InvalidArgumentException::class);
        app(BillingService::class)->cancel($this->studentBill->fresh());
    }

    public function test_bill_detail_page_is_reachable_and_shows_amounts(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('bills.show', $this->studentBill))
            ->assertOk()
            ->assertSee($this->studentBill->bill_number)
            ->assertSee('Siswa Cancel');
    }

    public function test_bill_detail_page_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('bills.show', $this->studentBill))->assertForbidden();
    }
}

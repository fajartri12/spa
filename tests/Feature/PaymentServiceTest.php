<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private PaymentCategory $category;
    private AcademicYear $year;
    private StudentBill $studentBill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $unit = Unit::query()->create(['code' => 'PAY', 'name' => 'Payment Test Unit']);
        $this->year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $this->year->id,
            'name' => 'X-PAY',
        ]);
        $this->category = PaymentCategory::query()->create([
            'code' => 'PAY-TEST',
            'name' => 'Payment Test',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 300000,
            'due_day' => 10,
            'is_active' => true,
        ]);
        $this->student = Student::query()->create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'PAY-001',
            'nisn' => 'PAY-NISN-001',
            'name' => 'Payment Student',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);
        $this->studentBill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
            'amount' => 300000,
        ]);
    }

    public function test_payment_is_pending_on_creation_and_does_not_reduce_balance_until_approved(): void
    {
        $payment = app(PaymentService::class)->create(
            ['student_id' => $this->student->id, 'transaction_number' => 'PAY-001', 'amount' => 100000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 100000]],
        );

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertDatabaseHas('student_bills', ['id' => $this->studentBill->id, 'paid_amount' => 0, 'remaining_amount' => 300000, 'status' => 'unpaid']);
    }

    public function test_approving_partial_payment_updates_paid_remaining_and_status(): void
    {
        $payment = app(PaymentService::class)->create(
            ['student_id' => $this->student->id, 'transaction_number' => 'PAY-002', 'amount' => 125000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 125000]],
        );

        app(PaymentService::class)->approve($payment);

        $this->assertDatabaseHas('student_bills', ['id' => $this->studentBill->id, 'paid_amount' => 125000, 'remaining_amount' => 175000, 'status' => 'partial']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'approved']);
    }

    public function test_payment_cannot_be_approved_twice_or_rejected_after_approval(): void
    {
        $payment = app(PaymentService::class)->create(
            ['student_id' => $this->student->id, 'transaction_number' => 'PAY-003', 'amount' => 50000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 50000]],
        );
        $service = app(PaymentService::class);
        $service->approve($payment);

        $this->expectException(InvalidArgumentException::class);
        $service->approve($payment);
    }

    public function test_full_payment_marks_bill_paid_while_rejected_payment_leaves_balance_untouched(): void
    {
        $service = app(PaymentService::class);
        $rejected = $service->create(
            ['student_id' => $this->student->id, 'transaction_number' => 'PAY-004', 'amount' => 10000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 10000]],
        );
        $service->reject($rejected, 'Bukti tidak valid');
        $this->assertDatabaseHas('student_bills', ['id' => $this->studentBill->id, 'paid_amount' => 0, 'remaining_amount' => 300000, 'status' => 'unpaid']);

        $payment = $service->create(
            ['student_id' => $this->student->id, 'transaction_number' => 'PAY-005', 'amount' => 300000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 300000]],
        );
        $service->approve($payment);

        $this->assertDatabaseHas('student_bills', ['id' => $this->studentBill->id, 'paid_amount' => 300000, 'remaining_amount' => 0, 'status' => 'paid']);

        $this->assertDatabaseHas('payments', ['id' => $rejected->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('student_bills', ['id' => $this->studentBill->id, 'paid_amount' => 300000, 'status' => 'paid']);
    }

    public function test_allocation_above_remaining_balance_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PaymentService::class)->create(
            ['student_id' => $this->student->id, 'transaction_number' => 'PAY-006', 'amount' => 350000],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 350000]],
        );
    }

    public function test_duplicate_transaction_number_is_rejected_without_changing_balance(): void
    {
        $service = app(PaymentService::class);
        $data = ['student_id' => $this->student->id, 'transaction_number' => 'PAY-DUPLICATE', 'amount' => 50000];
        $allocation = [['student_bill_id' => $this->studentBill->id, 'amount' => 50000]];
        $service->create($data, $allocation);

        $this->expectException(QueryException::class);
        $service->create($data, $allocation);
    }
}

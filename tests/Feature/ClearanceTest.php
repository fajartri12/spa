<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ClearanceStatus;
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
use App\Services\ClearanceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bebas tanggungan sebagai prasyarat SKL.
 */
class ClearanceTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private StudentBill $studentBill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $unit = Unit::query()->create(['code' => 'CL', 'name' => 'Unit Clearance']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'XII-CL',
        ]);
        $category = PaymentCategory::query()->create([
            'code' => 'CL-TEST',
            'name' => 'Clearance Test',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 400000,
            'due_day' => 10,
            'is_active' => true,
        ]);
        $this->student = Student::query()->create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'CL-001',
            'nisn' => 'CL-NISN-001',
            'name' => 'Clearance Student',
            'gender' => Gender::Male,
            'status' => StudentStatus::Graduated,
            'entry_year' => 2023,
        ]);
        $this->studentBill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'period' => '2026-11',
            'amount' => 400000,
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function treasurer(): User
    {
        return User::query()->where('email', 'bendahara@man7jombang.sch.id')->firstOrFail();
    }

    private function payOff(): void
    {
        $payment = app(PaymentService::class)->create(
            [
                'student_id' => $this->student->id,
                'transaction_number' => 'PAY-CL-1',
                'amount' => 400000,
                'paid_at' => now(),
            ],
            [['student_bill_id' => $this->studentBill->id, 'amount' => 400000]],
        );

        app(PaymentService::class)->approve($payment);
    }

    public function test_check_with_outstanding_bills_marks_pending(): void
    {
        $clearance = app(ClearanceService::class)->check($this->student, $this->superadmin());

        $this->assertSame(ClearanceStatus::Pending, $clearance->status);
        $this->assertSame('400000.00', (string) $clearance->remaining_amount);
        $this->assertSame(1, $clearance->outstanding_bill_count);
        $this->assertCount(1, $clearance->outstanding_bills);
        $this->assertFalse($clearance->canIssueSkl());
    }

    public function test_check_after_full_payment_marks_cleared(): void
    {
        $this->payOff();

        $clearance = app(ClearanceService::class)->check($this->student, $this->superadmin());

        $this->assertSame(ClearanceStatus::Cleared, $clearance->status);
        $this->assertSame('0.00', (string) $clearance->remaining_amount);
        $this->assertSame(0, $clearance->outstanding_bill_count);
        $this->assertTrue($clearance->canIssueSkl());
    }

    public function test_override_requires_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ClearanceService::class)->override($this->student, '  ', $this->treasurer());
    }

    public function test_override_allows_issuance_despite_outstanding(): void
    {
        $clearance = app(ClearanceService::class)
            ->override($this->student, 'Wali menandatangani surat pernyataan', $this->treasurer());

        $this->assertSame(ClearanceStatus::Overridden, $clearance->status);
        $this->assertSame('Wali menandatangani surat pernyataan', $clearance->override_reason);
        $this->assertTrue($clearance->canIssueSkl());
    }

    public function test_override_is_rejected_when_already_cleared(): void
    {
        $this->payOff();

        $this->expectException(InvalidArgumentException::class);

        app(ClearanceService::class)->override($this->student, 'Tidak perlu', $this->treasurer());
    }

    public function test_issue_skl_is_blocked_while_pending(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ClearanceService::class)->issueSkl($this->student, $this->treasurer());
    }

    public function test_issue_skl_generates_number_and_is_idempotent(): void
    {
        $this->payOff();

        $service = app(ClearanceService::class);
        $first = $service->issueSkl($this->student, $this->treasurer());

        $this->assertNotNull($first->skl_number);
        $this->assertStringStartsWith('SKL/'.now()->format('Y').'/', $first->skl_number);
        $this->assertNotNull($first->skl_issued_at);

        $second = $service->issueSkl($this->student, $this->treasurer());

        $this->assertSame($first->skl_number, $second->skl_number);
    }

    public function test_skl_number_increments(): void
    {
        $this->payOff();

        $service = app(ClearanceService::class);
        $first = $service->issueSkl($this->student, $this->treasurer());

        $other = Student::query()->create([
            'unit_id' => $this->student->unit_id,
            'school_class_id' => $this->student->school_class_id,
            'nis' => 'CL-002',
            'name' => 'Siswa Kedua',
            'gender' => Gender::Female,
            'status' => StudentStatus::Graduated,
            'entry_year' => 2023,
        ]);

        $second = $service->issueSkl($other, $this->treasurer());

        $this->assertNotSame($first->skl_number, $second->skl_number);
        $this->assertSame('SKL/'.now()->format('Y').'/0002', $second->skl_number);
    }

    public function test_skl_pdf_requires_issued_number(): void
    {
        $this->payOff();
        app(ClearanceService::class)->check($this->student, $this->treasurer());

        $this->actingAs($this->treasurer())
            ->get(route('clearances.skl', $this->student))
            ->assertNotFound();
    }

    public function test_skl_pdf_downloads_after_issuance(): void
    {
        $this->payOff();
        app(ClearanceService::class)->issueSkl($this->student, $this->treasurer());

        $response = $this->actingAs($this->treasurer())
            ->get(route('clearances.skl', $this->student));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_component_checks_and_issues_skl(): void
    {
        $this->payOff();

        $this->actingAs($this->treasurer());

        Livewire::test(\App\Livewire\Students\Clearances\ClearanceIndex::class)
            ->call('check', $this->student->id)
            ->call('issueSkl', $this->student->id);

        $this->assertDatabaseHas('graduation_clearances', [
            'student_id' => $this->student->id,
            'status' => ClearanceStatus::Cleared->value,
        ]);

        $this->assertNotNull($this->student->clearance()->first()?->skl_number);
    }

    public function test_component_override_requires_reason(): void
    {
        $this->actingAs($this->treasurer());

        Livewire::test(\App\Livewire\Students\Clearances\ClearanceIndex::class)
            ->call('startOverride', $this->student->id)
            ->call('override')
            ->assertHasErrors(['overrideReason']);
    }

    public function test_component_requires_permission(): void
    {
        $officer = User::query()->where('email', 'petugas@man7jombang.sch.id')->firstOrFail();

        $this->actingAs($officer);

        Livewire::test(\App\Livewire\Students\Clearances\ClearanceIndex::class)
            ->assertForbidden();
    }
}

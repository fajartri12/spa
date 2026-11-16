<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashTransactionType;
use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Bill;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private PaymentCategory $category;
    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $unit = Unit::query()->create(['code' => 'RPT', 'name' => 'Unit Laporan']);
        $this->year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create(['unit_id' => $unit->id, 'academic_year_id' => $this->year->id, 'name' => 'X-RPT']);
        $this->category = PaymentCategory::query()->create(['code' => 'RPT-CAT', 'name' => 'Kategori Laporan', 'type' => PaymentCategoryType::Monthly, 'default_amount' => 200000, 'due_day' => 10, 'is_active' => true]);
        $this->student = Student::query()->create(['unit_id' => $unit->id, 'school_class_id' => $class->id, 'nis' => 'RPT-001', 'nisn' => 'RPT-NISN-001', 'name' => 'Siswa Laporan', 'gender' => Gender::Male, 'status' => StudentStatus::Active, 'entry_year' => 2026]);
    }

    public function test_report_uses_only_approved_payments_and_returns_outstanding_bills(): void
    {
        $base = ['student_id' => $this->student->id, 'payment_category_id' => $this->category->id, 'academic_year_id' => $this->year->id, 'paid_at' => '2026-11-04 09:00:00'];
        Payment::query()->create($base + ['transaction_number' => 'RPT-APPROVED', 'amount' => 125000, 'status' => 'approved']);
        Payment::query()->create($base + ['transaction_number' => 'RPT-PENDING', 'amount' => 75000, 'status' => 'pending']);
        $account = Account::query()->create(['code' => 'RPT-1101', 'name' => 'Kas Laporan', 'type' => 'cash', 'opening_balance' => 0, 'is_active' => true]);
        CashTransaction::query()->create(['account_id' => $account->id, 'transaction_number' => 'KAS-RPT', 'transacted_at' => '2026-11-04 10:00:00', 'type' => CashTransactionType::Income, 'amount' => 50000, 'description' => 'Penerimaan kas']);
        $bill = Bill::query()->create(['payment_category_id' => $this->category->id, 'academic_year_id' => $this->year->id, 'bill_number' => 'BILL-RPT', 'title' => 'Tagihan Laporan', 'period' => '2026-11', 'due_date' => '2026-11-10', 'amount' => 200000, 'original_amount' => 200000, 'total_amount' => 200000, 'status' => 'unpaid']);
        $studentBill = StudentBill::query()->create(['bill_id' => $bill->id, 'student_id' => $this->student->id, 'bill_number' => 'TAG-RPT', 'original_amount' => 200000, 'final_amount' => 200000, 'paid_amount' => 0, 'remaining_amount' => 200000, 'status' => 'unpaid']);
        $report = app(FinancialReportService::class)->report('2026-11-01', '2026-11-30');
        $this->assertSame(125000.0, $report['total_income']);
        $this->assertSame(50000.0, $report['cash_income']);
        $this->assertSame(200000.0, $report['outstanding']);
        $this->assertSame(['RPT-APPROVED'], $report['recent']->pluck('transaction_number')->all());
        $this->assertTrue($report['outstanding_bills']->contains($studentBill));
    }

    public function test_export_returns_approved_payments_csv_and_requires_export_permission(): void
    {
        Payment::query()->create(['student_id' => $this->student->id, 'payment_category_id' => $this->category->id, 'academic_year_id' => $this->year->id, 'transaction_number' => 'CSV-APPROVED', 'paid_at' => '2026-11-04 09:00:00', 'amount' => 125000, 'status' => 'approved']);
        $user = User::factory()->create();
        $user->givePermissionTo('reports.export');

        $response = $this->actingAs($user)->get(route('reports.export', ['type' => 'approved_payments', 'from' => '2026-11-01', 'to' => '2026-11-30']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('CSV-APPROVED', $response->streamedContent());
    }

    public function test_export_returns_outstanding_bills_csv(): void
    {
        $bill = Bill::query()->create(['payment_category_id' => $this->category->id, 'academic_year_id' => $this->year->id, 'bill_number' => 'BILL-CSV', 'title' => 'Tagihan CSV', 'period' => '2026-11', 'due_date' => '2026-11-10', 'amount' => 200000, 'original_amount' => 200000, 'total_amount' => 200000, 'status' => 'unpaid']);
        StudentBill::query()->create(['bill_id' => $bill->id, 'student_id' => $this->student->id, 'bill_number' => 'TAG-CSV-OUTSTANDING', 'original_amount' => 200000, 'final_amount' => 200000, 'paid_amount' => 0, 'remaining_amount' => 200000, 'status' => 'unpaid']);
        $user = User::factory()->create();
        $user->givePermissionTo('reports.export');

        $response = $this->actingAs($user)->get(route('reports.export', ['type' => 'outstanding_bills']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('TAG-CSV-OUTSTANDING', $response->streamedContent());
    }

    public function test_export_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.export', ['type' => 'approved_payments']))
            ->assertForbidden();
    }

    public function test_report_export_validates_type(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('reports.export');
        $this->actingAs($user)->get(route('reports.export', ['type' => 'invalid']))->assertSessionHasErrors('type');
    }

    public function test_report_page_shows_export_links_for_authorized_user(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'reports.export']);

        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertSee('Ekspor CSV');
    }

    public function test_report_page_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertForbidden();
    }
}

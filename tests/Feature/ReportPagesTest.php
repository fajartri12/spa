<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashTransactionType;
use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPagesTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private PaymentCategory $category;
    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $unit = Unit::query()->create(['code' => 'PGT', 'name' => 'Unit Laporan Halaman']);
        $this->year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create(['unit_id' => $unit->id, 'academic_year_id' => $this->year->id, 'name' => 'X-PGT']);
        $this->category = PaymentCategory::query()->create(['code' => 'PGT-CAT', 'name' => 'Kategori PGT', 'type' => PaymentCategoryType::Monthly, 'default_amount' => 100000, 'due_day' => 10, 'is_active' => true]);
        $this->student = Student::query()->create(['unit_id' => $unit->id, 'school_class_id' => $class->id, 'nis' => 'PGT-001', 'nisn' => 'PGT-NISN-001', 'name' => 'Siswa PGT', 'gender' => Gender::Male, 'status' => StudentStatus::Active, 'entry_year' => 2026]);
    }

    private function payment(string $number, float $amount, string $status, string $paidAt): Payment
    {
        return Payment::query()->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'transaction_number' => $number,
            'paid_at' => $paidAt,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    public function test_transaction_page_lists_payments_and_totals(): void
    {
        $this->payment('PGT-APPROVED', 125000, 'approved', now()->toDateTimeString());
        $this->payment('PGT-PENDING', 75000, 'pending', now()->toDateTimeString());

        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'reports.export']);

        $this->actingAs($user)->get(route('reports.transactions'))
            ->assertOk()
            ->assertSee('PGT-APPROVED')
            ->assertSee('PGT-PENDING')
            ->assertSee('125.000');
    }

    public function test_transaction_page_groups_summary_into_bill_other_cash_and_non_cash(): void
    {
        $studentBill = app(\App\Services\BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => $this->year->id,
            'period' => '2026-11',
            'amount' => 100000,
            'due_date' => now()->toDateString(),
        ]);

        $billPayment = $this->payment('PGT-BILL', 100000, 'approved', now()->toDateTimeString());
        $billPayment->allocations()->create(['student_bill_id' => $studentBill->id, 'amount' => 100000]);

        $cashMethod = \App\Models\PaymentMethod::query()->where('code', 'cash')->firstOrFail();
        $cashPayment = $this->payment('PGT-CASH', 50000, 'approved', now()->toDateTimeString());
        $cashPayment->details()->create(['payment_method_id' => $cashMethod->id, 'amount' => 50000]);

        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'reports.export']);

        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Reports\TransactionIndex::class)
            ->assertViewHas('summary', fn (array $summary): bool => $summary === [
                'total_count' => 2,
                'total_amount' => 150000.0,
                'bill_amount' => 100000.0,
                'bill_count' => 1,
                'other_amount' => 50000.0,
                'other_count' => 1,
                'cash_amount' => 50000.0,
                'cash_count' => 1,
                'non_cash_amount' => 100000.0,
                'non_cash_count' => 1,
            ]);
    }

    public function test_transaction_page_requires_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.transactions'))->assertForbidden();
    }

    public function test_cash_flow_page_lists_transactions_and_computes_balance(): void
    {
        $account = Account::query()->create(['code' => 'PGT-1101', 'name' => 'Kas PGT', 'type' => 'cash', 'opening_balance' => 0, 'is_active' => true]);
        CashTransaction::query()->create(['account_id' => $account->id, 'transaction_number' => 'KAS-PGT-IN', 'transacted_at' => now(), 'type' => CashTransactionType::Income, 'amount' => 300000, 'description' => 'Pemasukan PGT']);
        CashTransaction::query()->create(['account_id' => $account->id, 'transaction_number' => 'KAS-PGT-OUT', 'transacted_at' => now(), 'type' => CashTransactionType::Expense, 'amount' => 100000, 'description' => 'Pengeluaran PGT']);

        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'reports.export']);

        $this->actingAs($user)->get(route('reports.cash-flow'))
            ->assertOk()
            ->assertSee('KAS-PGT-IN')
            ->assertSee('KAS-PGT-OUT')
            ->assertSee('200.000');
    }

    public function test_cash_flow_page_requires_view_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.cash-flow'))->assertForbidden();
    }

    public function test_print_options_page_renders_for_authorized_user(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'reports.export']);

        $this->actingAs($user)->get(route('reports.print'))
            ->assertOk()
            ->assertSee('Cetak Laporan')
            ->assertSee('Laporan Arus Kas');
    }

    public function test_print_download_returns_pdf_for_each_type(): void
    {
        $this->payment('PGT-PDF', 50000, 'approved', now()->toDateTimeString());
        $account = Account::query()->create(['code' => 'PGT-1102', 'name' => 'Kas PDF', 'type' => 'cash', 'opening_balance' => 0, 'is_active' => true]);
        CashTransaction::query()->create(['account_id' => $account->id, 'transaction_number' => 'KAS-PDF', 'transacted_at' => now(), 'type' => CashTransactionType::Income, 'amount' => 50000, 'description' => 'Kas PDF']);

        $user = User::factory()->create();
        $user->givePermissionTo(['reports.view', 'reports.export']);

        foreach (['approved_payments', 'outstanding_bills', 'cash_flow'] as $type) {
            $this->actingAs($user)->get(route('reports.print.download', ['type' => $type]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_print_download_requires_export_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.print.download', ['type' => 'approved_payments']))
            ->assertForbidden();
    }

    public function test_print_download_validates_type(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('reports.export');

        $this->actingAs($user)
            ->get(route('reports.print.download', ['type' => 'invalid']))
            ->assertSessionHasErrors('type');
    }
}

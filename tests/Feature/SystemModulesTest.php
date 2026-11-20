<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\PaymentCategory;
use App\Models\ScholarshipAward;
use App\Models\ScholarshipProgram;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\BudgetService;
use App\Services\DiscountService;
use App\Services\LedgerService;
use App\Services\SavingsService;
use App\Services\ScholarshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Student $student;
    private PaymentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'scholarships.view',
            'scholarships.create',
            'scholarships.update',
            'scholarships.delete',
            'scholarships.award',
            'budgets.view',
            'budgets.update',
            'reports.view',
            'savings.view',
            'savings.create',
            'savings.withdraw',
            'payments.create',
        ]);

        $unit = Unit::query()->create(['code' => 'MOD', 'name' => 'Unit Modul']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Kelas Modul',
        ]);

        $this->category = PaymentCategory::query()->create([
            'code' => 'MOD-CAT',
            'name' => 'Kategori Modul',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 150000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $this->student = Student::query()->create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'MOD-001',
            'nisn' => 'MOD-NISN-001',
            'name' => 'Siswa Modul',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);

        Account::query()->create([
            'code' => '101',
            'name' => 'Kas Modul',
            'type' => 'cash',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
    }

    public function test_scholarship_program_creation_and_award_lifecycle(): void
    {
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Master\ScholarshipIndex::class)
            ->set('code', 'BS-TEST-2026')
            ->set('name', 'Beasiswa Test')
            ->set('funding_source', 'internal')
            ->set('discount_type', DiscountService::TYPE_PERCENT)
            ->set('discount_value', '50')
            ->call('saveProgram')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $program = ScholarshipProgram::query()->where('code', 'BS-TEST-2026')->firstOrFail();
        $this->assertEquals('Beasiswa Test', $program->name);
        $this->assertEquals(50, (float) $program->discount_value);

        // Award scholarship to student
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Master\ScholarshipIndex::class)
            ->set('selectedProgramId', $program->id)
            ->set('student_id', $this->student->id)
            ->set('award_notes', 'Penerima test')
            ->call('awardScholarship')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $award = ScholarshipAward::query()
            ->where('scholarship_program_id', $program->id)
            ->where('student_id', $this->student->id)
            ->firstOrFail();

        $this->assertTrue($award->isActive());
        $this->assertNotNull($award->student_discount_id);

        // Revoke
        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Master\ScholarshipIndex::class)
            ->set('revokingAwardId', $award->id)
            ->set('revocation_reason', 'Lulus kuliah')
            ->call('revokeAward')
            ->assertHasNoErrors();

        $this->assertEquals('revoked', $award->fresh()->status);
    }

    public function test_budget_save_and_comparison(): void
    {
        $year = AcademicYear::query()->firstOrFail();
        $account = Account::query()->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(\App\Livewire\Finance\BudgetIndex::class)
            ->set('academic_year_id', $year->id)
            ->set('amounts.'.$account->id, '5000000')
            ->set('notes.'.$account->id, 'Target anggaran test')
            ->call('saveBudget')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success');

        $this->assertDatabaseHas('budgets', [
            'academic_year_id' => $year->id,
            'account_id' => $account->id,
            'planned_amount' => 5000000,
        ]);

        $service = app(BudgetService::class);
        $comparison = $service->compare($year->id);
        $row = $comparison->firstWhere('account.id', $account->id);

        $this->assertNotNull($row);
        $this->assertEquals(5000000, $row->planned);
    }

    public function test_pay_bills_from_savings_without_double_counting_cash(): void
    {
        $savingsService = app(SavingsService::class);

        // 1. Deposit cash into savings (this SHOULD record cash income)
        $savingsService->deposit($this->student, 200000, 'Deposit awal', $this->admin);

        $account = \App\Models\StudentSavingsAccount::query()->where('student_id', $this->student->id)->firstOrFail();
        $this->assertEquals(200000, (float) $account->balance);

        // 2. Create a bill for the student
        $studentBill = app(\App\Services\BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $this->category->id,
            'academic_year_id' => AcademicYear::query()->first()->id,
            'period' => '2026-11',
            'amount' => 150000,
            'due_date' => now()->toDateString(),
        ]);

        $initialCashIncomeCount = \App\Models\CashTransaction::query()
            ->where('type', \App\Enums\CashTransactionType::Income->value)
            ->count();

        // 3. Pay bill from savings
        $payment = $savingsService->payBills(
            $this->student,
            [['student_bill_id' => $studentBill->id, 'amount' => 150000]],
            'Bayar dari tabungan',
            $this->admin
        );

        $this->assertEquals('approved', $payment->status->value);
        $this->assertEquals(50000, (float) $account->fresh()->balance);
        $this->assertEquals('paid', $studentBill->fresh()->status->value);

        // Verify NO duplicate cash income was recorded for the bill payment from savings
        $finalCashIncomeCount = \App\Models\CashTransaction::query()
            ->where('type', \App\Enums\CashTransactionType::Income->value)
            ->count();

        $this->assertEquals($initialCashIncomeCount, $finalCashIncomeCount);
    }

    public function test_ledger_trial_balance_and_analytics_services(): void
    {
        $ledger = app(LedgerService::class);
        $tb = $ledger->trialBalance();
        $this->assertNotEmpty($tb);

        $analytics = app(AnalyticsService::class);
        $aging = $analytics->aging();
        $this->assertArrayHasKey('buckets', $aging);
        $this->assertArrayHasKey('total', $aging);

        $collection = $analytics->collectionRate();
        $this->assertArrayHasKey('rows', $collection);

        $forecast = $analytics->forecast();
        $this->assertArrayHasKey('projection', $forecast);
    }
}

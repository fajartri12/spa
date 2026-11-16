<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashTransactionType;
use App\Enums\JournalStatus;
use App\Enums\PayrollStatus;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Journal;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Penggajian / honorarium: slip, persetujuan, dan pembatalan.
 */
class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(AccountSeeder::class);

        $this->cashAccount = Account::query()->where('code', '1101')->firstOrFail();
        $this->expenseAccount = Account::query()->where('code', '5102')->firstOrFail();
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function treasurer(): User
    {
        return User::query()->where('email', 'bendahara@man7jombang.sch.id')->firstOrFail();
    }

    private function makePeriod(array $overrides = []): PayrollPeriod
    {
        return app(PayrollService::class)->createPeriod(array_merge([
            'period' => now()->format('Y-m'),
            'title' => 'Honorarium Test',
            'cash_account_id' => $this->cashAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
        ], $overrides), $this->superadmin());
    }

    private function addItem(PayrollPeriod $period, array $overrides = []): PayrollItem
    {
        return app(PayrollService::class)->addItem($period, array_merge([
            'recipient_name' => 'Ustadz Ahmad',
            'recipient_position' => 'Guru',
            'gross_amount' => 1500000,
            'deduction_amount' => 50000,
        ], $overrides), $this->superadmin());
    }

    public function test_period_is_created_as_draft_with_zero_total(): void
    {
        $period = $this->makePeriod();

        $this->assertSame(PayrollStatus::Draft, $period->status);
        $this->assertSame('0.00', (string) $period->total_amount);
        $this->assertSame(0, $period->employee_count);
    }

    public function test_adding_item_computes_net_and_updates_totals(): void
    {
        $period = $this->makePeriod();
        $item = $this->addItem($period);

        $this->assertSame('1450000.00', (string) $item->net_amount);

        $period->refresh();
        $this->assertSame('1450000.00', (string) $period->total_amount);
        $this->assertSame(1, $period->employee_count);
    }

    public function test_deduction_cannot_exceed_gross(): void
    {
        $period = $this->makePeriod();

        $this->expectException(InvalidArgumentException::class);

        $this->addItem($period, ['gross_amount' => 100000, 'deduction_amount' => 200000]);
    }

    public function test_approve_creates_single_cash_transaction_and_balanced_journal(): void
    {
        $period = $this->makePeriod();
        $this->addItem($period, ['recipient_name' => 'Guru A', 'gross_amount' => 1000000, 'deduction_amount' => 0]);
        $this->addItem($period, ['recipient_name' => 'Guru B', 'gross_amount' => 750000, 'deduction_amount' => 0]);

        $approved = app(PayrollService::class)->approve($period, $this->treasurer());

        $this->assertSame(PayrollStatus::Approved, $approved->status);
        $this->assertSame('1750000.00', (string) $approved->total_amount);
        $this->assertSame(2, $approved->employee_count);
        $this->assertNotNull($approved->approved_at);

        $this->assertSame(1, CashTransaction::query()->where('type', CashTransactionType::Expense->value)->count());

        $transaction = CashTransaction::query()->findOrFail($approved->cash_transaction_id);
        $this->assertSame('1750000.00', (string) $transaction->amount);
        $this->assertSame($this->cashAccount->id, $transaction->account_id);

        $journal = Journal::query()->findOrFail($approved->journal_id);
        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertCount(2, $journal->details);
        $this->assertSame(
            (float) $journal->details()->sum('debit'),
            (float) $journal->details()->sum('credit'),
        );
        $this->assertSame(1750000.0, (float) $journal->details()->sum('debit'));
    }

    public function test_approve_requires_at_least_one_item(): void
    {
        $period = $this->makePeriod();

        $this->expectException(InvalidArgumentException::class);

        app(PayrollService::class)->approve($period, $this->treasurer());
    }

    public function test_items_cannot_be_changed_after_approval(): void
    {
        $period = $this->makePeriod();
        $item = $this->addItem($period);

        app(PayrollService::class)->approve($period, $this->treasurer());

        $this->expectException(InvalidArgumentException::class);

        app(PayrollService::class)->updateItem($item, ['gross_amount' => 2000000], $this->superadmin());
    }

    public function test_cancel_voids_journal_and_removes_cash_row(): void
    {
        $period = $this->makePeriod();
        $this->addItem($period);

        $approved = app(PayrollService::class)->approve($period, $this->treasurer());
        $transactionId = $approved->cash_transaction_id;
        $journalId = $approved->journal_id;

        $cancelled = app(PayrollService::class)->cancel($approved, 'Salah input nominal', $this->treasurer());

        $this->assertSame(PayrollStatus::Cancelled, $cancelled->status);
        $this->assertSame('Salah input nominal', $cancelled->cancellation_reason);
        $this->assertSoftDeleted('cash_transactions', ['id' => $transactionId]);
        $this->assertSame(JournalStatus::Voided, Journal::query()->findOrFail($journalId)->status);
    }

    public function test_cancel_requires_reason(): void
    {
        $period = $this->makePeriod();
        $this->addItem($period);
        $approved = app(PayrollService::class)->approve($period, $this->treasurer());

        $this->expectException(InvalidArgumentException::class);

        app(PayrollService::class)->cancel($approved, '   ', $this->treasurer());
    }

    public function test_component_creates_period_and_redirects(): void
    {
        $this->actingAs($this->treasurer());

        Livewire::test(\App\Livewire\Payroll\PayrollIndex::class)
            ->call('openCreate')
            ->set('period', now()->format('Y-m'))
            ->set('title', 'Honorarium Komponen')
            ->set('cashAccountId', $this->cashAccount->id)
            ->set('expenseAccountId', $this->expenseAccount->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payroll_periods', ['title' => 'Honorarium Komponen', 'status' => 'draft']);
    }

    public function test_component_adds_item_and_approves(): void
    {
        $period = $this->makePeriod();

        $this->actingAs($this->treasurer());

        Livewire::test(\App\Livewire\Payroll\PayrollShow::class, ['payrollPeriod' => $period])
            ->call('openCreate')
            ->set('recipientName', 'Ustadzah Fatimah')
            ->set('recipientPosition', 'Guru')
            ->set('grossAmount', '1200000')
            ->set('deductionAmount', '0')
            ->call('saveItem')
            ->assertHasNoErrors()
            ->assertSet('showItemModal', false)
            ->call('confirmApprove')
            ->set('transactedAt', now()->toDateString())
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSet('showApproveModal', false);

        $period->refresh();
        $this->assertSame(PayrollStatus::Approved, $period->status);
        $this->assertSame('1200000.00', (string) $period->total_amount);
    }

    public function test_component_requires_permission(): void
    {
        $verifier = User::query()->where('email', 'verifikator@man7jombang.sch.id')->firstOrFail();

        $this->actingAs($verifier);

        Livewire::test(\App\Livewire\Payroll\PayrollIndex::class)
            ->assertForbidden();
    }

    public function test_staff_selection_fills_recipient_name(): void
    {
        $staff = StaffProfile::query()->create([
            'unit_id' => \App\Models\Unit::query()->value('id'),
            'nip' => 'NIP-TEST-1',
            'name' => 'Ustadz Salman',
            'gender' => \App\Enums\Gender::Male,
            'position' => \App\Enums\StaffPosition::Teacher,
            'is_active' => true,
        ]);

        $period = $this->makePeriod();

        $this->actingAs($this->treasurer());

        Livewire::test(\App\Livewire\Payroll\PayrollShow::class, ['payrollPeriod' => $period])
            ->call('openCreate')
            ->set('staffProfileId', $staff->id)
            ->assertSet('recipientName', 'Ustadz Salman');
    }

    public function test_slip_pdf_can_be_downloaded_by_authorised_user(): void
    {
        $period = $this->makePeriod();
        $this->addItem($period, ['recipient_name' => 'Guru Slip']);

        $response = $this->actingAs($this->treasurer())
            ->get(route('payrolls.slips', $period));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'slip-honorarium-'.$period->period.'.pdf',
            (string) $response->headers->get('content-disposition'),
        );
    }

    public function test_slip_pdf_requires_payroll_permission(): void
    {
        $period = $this->makePeriod();
        $this->addItem($period);

        $verifier = User::query()->where('email', 'verifikator@man7jombang.sch.id')->firstOrFail();

        $this->actingAs($verifier)
            ->get(route('payrolls.slips', $period))
            ->assertForbidden();
    }

    public function test_slip_pdf_is_not_available_for_empty_period(): void
    {
        $period = $this->makePeriod();

        $this->actingAs($this->treasurer())
            ->get(route('payrolls.slips', $period))
            ->assertNotFound();
    }
}

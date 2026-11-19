<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashTransactionType;
use App\Enums\JournalStatus;
use App\Livewire\Cash\CashExpenseIndex;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Journal;
use App\Models\User;
use App\Services\CashExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class CashExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Account $cashAccount;

    private Account $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(\Database\Seeders\AccountSeeder::class);

        $this->cashAccount = Account::query()->where('code', '1101')->firstOrFail();
        $this->expenseAccount = Account::query()->where('code', '5101')->firstOrFail();
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function treasurer(): User
    {
        return User::query()->where('email', 'bendahara@email.com')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'account_id' => $this->cashAccount->id,
            'expense_account_id' => $this->expenseAccount->id,
            'amount' => 150000,
            'transacted_at' => now()->toDateString(),
            'description' => 'Pembelian ATK',
        ], $overrides);
    }

    public function test_service_records_expense_and_creates_balanced_journal(): void
    {
        $transaction = app(CashExpenseService::class)
            ->record($this->payload(), $this->superadmin());

        $this->assertSame(CashTransactionType::Expense, $transaction->type);
        $this->assertSame('150000.00', (string) $transaction->amount);
        $this->assertSame($this->cashAccount->id, $transaction->account_id);

        $journal = Journal::query()->where('reference_number', $transaction->transaction_number)->firstOrFail();

        $this->assertSame(JournalStatus::Posted, $journal->status);
        $this->assertCount(2, $journal->details);

        $debit = (float) $journal->details()->sum('debit');
        $credit = (float) $journal->details()->sum('credit');

        $this->assertSame($debit, $credit);
        $this->assertSame(150000.0, $debit);

        $debitLine = $journal->details()->where('debit', '>', 0)->firstOrFail();
        $creditLine = $journal->details()->where('credit', '>', 0)->firstOrFail();

        $this->assertSame($this->expenseAccount->id, $debitLine->account_id);
        $this->assertSame($this->cashAccount->id, $creditLine->account_id);
    }

    public function test_transaction_numbers_increment_per_day(): void
    {
        $service = app(CashExpenseService::class);

        $first = $service->record($this->payload(), $this->superadmin());
        $second = $service->record($this->payload(['description' => 'Pembelian tinta']), $this->superadmin());

        $this->assertSame('KELUAR-'.now()->format('Ymd').'-00001', $first->transaction_number);
        $this->assertSame('KELUAR-'.now()->format('Ymd').'-00002', $second->transaction_number);
    }

    public function test_service_rejects_zero_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CashExpenseService::class)->record($this->payload(['amount' => 0]), $this->superadmin());
    }

    public function test_service_rejects_non_expense_target_account(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CashExpenseService::class)->record(
            $this->payload(['expense_account_id' => $this->cashAccount->id]),
            $this->superadmin(),
        );
    }

    public function test_service_rejects_non_cash_source_account(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(CashExpenseService::class)->record(
            $this->payload(['account_id' => $this->expenseAccount->id]),
            $this->superadmin(),
        );
    }

    public function test_treasurer_can_record_expense_from_component(): void
    {
        $this->actingAs($this->treasurer());

        Livewire::test(CashExpenseIndex::class)
            ->call('create')
            ->set('cash_account_id', $this->cashAccount->id)
            ->set('expense_account_id', $this->expenseAccount->id)
            ->set('amount', '275.000')
            ->set('transacted_at', now()->toDateString())
            ->set('description', 'Konsumsi rapat')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false);

        $transaction = CashTransaction::query()->latest('id')->firstOrFail();

        $this->assertSame('275000.00', (string) $transaction->amount);
        $this->assertSame('Konsumsi rapat', $transaction->description);
        $this->assertNotNull(Journal::query()->where('reference_number', $transaction->transaction_number)->first());
    }

    public function test_component_validates_required_fields(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(CashExpenseIndex::class)
            ->call('create')
            ->set('amount', '0')
            ->call('save')
            ->assertHasErrors(['cash_account_id', 'expense_account_id', 'amount', 'description']);
    }

    public function test_component_lists_only_expense_transactions(): void
    {
        CashTransaction::query()->create([
            'account_id' => $this->cashAccount->id,
            'user_id' => $this->superadmin()->id,
            'transaction_number' => 'KAS-MASUK-1',
            'transacted_at' => now(),
            'type' => CashTransactionType::Income->value,
            'amount' => 500000,
            'description' => 'Pemasukan lain',
        ]);

        $this->actingAs($this->superadmin());

        Livewire::test(CashExpenseIndex::class)
            ->assertSee('Belum Ada Pengeluaran')
            ->assertDontSee('Pemasukan lain');
    }

    public function test_void_marks_journal_voided_and_removes_cash_row(): void
    {
        $transaction = app(CashExpenseService::class)->record($this->payload(), $this->superadmin());

        Livewire::actingAs($this->treasurer())
            ->test(CashExpenseIndex::class)
            ->call('confirmVoid', $transaction->id)
            ->set('voidReason', 'Salah input nominal')
            ->call('void')
            ->assertHasNoErrors()
            ->assertSet('showVoidModal', false);

        $this->assertSoftDeleted('cash_transactions', ['id' => $transaction->id]);
        $this->assertSame(
            JournalStatus::Voided,
            Journal::query()->where('reference_number', $transaction->transaction_number)->firstOrFail()->status,
        );
    }

    public function test_void_requires_reason(): void
    {
        $transaction = app(CashExpenseService::class)->record($this->payload(), $this->superadmin());

        Livewire::actingAs($this->treasurer())
            ->test(CashExpenseIndex::class)
            ->call('confirmVoid', $transaction->id)
            ->call('void')
            ->assertHasErrors(['voidReason']);

        $this->assertNotSoftDeleted('cash_transactions', ['id' => $transaction->id]);
    }
}

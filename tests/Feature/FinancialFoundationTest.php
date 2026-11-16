<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashTransactionType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Journal;
use App\Models\JournalDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_tables_have_expected_relationships_and_casts(): void
    {
        $account = Account::query()->create(['code' => '1101', 'name' => 'Kas Utama', 'type' => 'cash', 'opening_balance' => 0, 'is_active' => true]);
        $journal = Journal::query()->create(['journal_number' => 'JRN-001', 'journal_date' => '2026-11-05', 'description' => 'Saldo awal', 'status' => JournalStatus::Posted]);
        $detail = $journal->details()->create(['account_id' => $account->id, 'debit' => 100000, 'credit' => 0]);
        $transaction = CashTransaction::query()->create(['account_id' => $account->id, 'transaction_number' => 'KAS-001', 'transacted_at' => '2026-11-05 10:00:00', 'type' => CashTransactionType::Income, 'amount' => 100000, 'description' => 'Penerimaan']);

        $this->assertInstanceOf(JournalDetail::class, $detail);
        $this->assertSame(JournalStatus::Posted, $journal->fresh()->status);
        $this->assertSame(CashTransactionType::Income, $transaction->fresh()->type);
        $this->assertSame('100000.00', $transaction->fresh()->amount);
        $this->assertTrue($account->fresh()->journalDetails->contains($detail));
        $this->assertTrue($account->fresh()->cashTransactions->contains($transaction));
    }

    public function test_transaction_number_and_journal_number_are_unique(): void
    {
        $account = Account::query()->create(['code' => '1102', 'name' => 'Kas', 'type' => 'cash', 'opening_balance' => 0, 'is_active' => true]);
        CashTransaction::query()->create(['account_id' => $account->id, 'transaction_number' => 'KAS-DUP', 'transacted_at' => now(), 'type' => CashTransactionType::Expense, 'amount' => 1, 'description' => 'Test']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        CashTransaction::query()->create(['account_id' => $account->id, 'transaction_number' => 'KAS-DUP', 'transacted_at' => now(), 'type' => CashTransactionType::Expense, 'amount' => 1, 'description' => 'Test']);
    }
}

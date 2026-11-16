<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CashTransactionType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pencatatan pengeluaran kas beserta jurnal double-entry.
 *
 * Setiap pengeluaran menghasilkan dua baris jurnal: debit akun beban tujuan dan
 * kredit akun kas/bank sumber dengan nominal identik. Ini menjaga laporan arus
 * kas dan neraca tetap seimbang tanpa input manual.
 */
final class CashExpenseService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly JournalService $journals,
    ) {}

    /**
     * @param  array{account_id:int, expense_account_id:int, amount:numeric, transacted_at:string, description:string, reference_number?:?string}  $data
     */
    public function record(array $data, User $actor): CashTransaction
    {
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal pengeluaran harus lebih dari nol.');
        }

        return DB::transaction(function () use ($data, $amount, $actor): CashTransaction {
            $cashAccount = Account::query()->lockForUpdate()->find($data['account_id']);
            $expenseAccount = Account::query()->lockForUpdate()->find($data['expense_account_id']);

            if ($cashAccount === null) {
                throw new InvalidArgumentException('Akun kas/bank sumber tidak ditemukan.');
            }

            if (! in_array($cashAccount->type, [AccountType::Cash, AccountType::Bank], true)) {
                throw new InvalidArgumentException('Akun sumber harus berjenis kas atau bank.');
            }

            if ($expenseAccount === null) {
                throw new InvalidArgumentException('Akun beban tidak ditemukan.');
            }

            if ($expenseAccount->type !== AccountType::Expense) {
                throw new InvalidArgumentException('Akun tujuan pengeluaran harus berjenis beban.');
            }

            $number = $this->nextNumber();

            $transaction = CashTransaction::query()->create([
                'account_id' => $cashAccount->id,
                'user_id' => $actor->id,
                'transaction_number' => $number,
                'transacted_at' => $data['transacted_at'],
                'type' => CashTransactionType::Expense->value,
                'amount' => $amount,
                'description' => $data['description'],
                'reference_number' => $data['reference_number'] ?? null,
            ]);

            $journal = $this->journals->post(
                (string) $data['transacted_at'],
                $data['description'],
                $number,
                [
                    ['account_id' => $expenseAccount->id, 'debit' => $amount],
                    ['account_id' => $cashAccount->id, 'credit' => $amount],
                ],
                $actor,
            );

            $this->logger->log(
                'cash.expense',
                "Mencatat pengeluaran kas {$number}",
                $transaction,
                ['journal' => $journal->journal_number, 'amount' => $amount],
                $actor,
            );

            return $transaction;
        });
    }

    /**
     * Batalkan pengeluaran yang salah input.
     *
     * Baris kas di-soft-delete (sehingga tidak lagi mengurangi saldo) dan
     * jurnal terkait diubah menjadi `voided`, bukan dihapus, agar jejak audit
     * tetap utuh.
     */
    public function void(CashTransaction $transaction, string $reason, User $actor): void
    {
        if ($transaction->type !== CashTransactionType::Expense) {
            throw new InvalidArgumentException('Hanya pengeluaran kas yang dapat dibatalkan di sini.');
        }

        DB::transaction(function () use ($transaction, $reason, $actor): void {
            Journal::query()
                ->where('reference_number', $transaction->transaction_number)
                ->update(['status' => JournalStatus::Voided->value]);

            $transaction->delete();

            $this->logger->log(
                'cash.expense_voided',
                "Pengeluaran {$transaction->transaction_number} dibatalkan: {$reason}",
                $transaction,
                ['amount' => (float) $transaction->amount, 'reason' => $reason],
                $actor,
            );
        });
    }

    /**
     * Nomor pengeluaran berurutan per hari.
     */
    private function nextNumber(): string
    {
        $prefix = 'KELUAR-'.now()->format('Ymd').'-';
        $last = CashTransaction::withTrashed()
            ->where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}

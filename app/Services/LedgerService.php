<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buku besar, neraca saldo, dan laba-rugi dari jurnal yang sudah posted.
 *
 * Konvensi saldo: positif berarti debit. Akun bersaldo normal kredit (mis.
 * pendapatan) karena itu tampil negatif, sesuai kaidah neraca saldo.
 */
final class LedgerService
{
    /**
     * @return Collection<int, object>
     */
    public function trialBalance(?int $academicYearId = null, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        [$from, $to] = $this->resolveRange($academicYearId, $from, $to);
        $movements = $this->movements(null, $from, $to);

        return Account::query()->ordered()->get()->map(function (Account $account) use ($movements): object {
            $row = $movements[$account->id] ?? null;
            $debit = round((float) ($row->debit ?? 0), 2);
            $credit = round((float) ($row->credit ?? 0), 2);
            $opening = round((float) $account->opening_balance, 2);
            $openingDebit = $this->isDebitNormal($account) ? $opening : 0.0;
            $openingCredit = $this->isDebitNormal($account) ? 0.0 : $opening;

            return (object) [
                'account' => $account,
                'opening_debit' => $openingDebit,
                'opening_credit' => $openingCredit,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($openingDebit - $openingCredit + $debit - $credit, 2),
            ];
        });
    }

    /**
     * Buku besar satu akun beserta saldo berjalan.
     *
     * @return array{account: Account, opening: float, rows: array<int, array<string, mixed>>, closing: float, debit: float, credit: float}
     */
    public function accountLedger(
        int $accountId,
        ?int $academicYearId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        [$from, $to] = $this->resolveRange($academicYearId, $from, $to);
        $account = Account::query()->findOrFail($accountId);

        $opening = $this->isDebitNormal($account)
            ? (float) $account->opening_balance
            : -(float) $account->opening_balance;

        if ($from !== null) {
            $prior = $this->movements($account->id, null, $from->copy()->subDay())[$account->id] ?? null;
            $opening += (float) ($prior->debit ?? 0) - (float) ($prior->credit ?? 0);
        }

        $rows = DB::table('journal_details')
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journal_details.account_id', $account->id)
            ->where('journals.status', JournalStatus::Posted->value)
            ->whereNull('journals.deleted_at')
            ->when($from !== null, fn ($query) => $query->whereDate('journals.journal_date', '>=', $from->toDateString()))
            ->when($to !== null, fn ($query) => $query->whereDate('journals.journal_date', '<=', $to->toDateString()))
            ->orderBy('journals.journal_date')
            ->orderBy('journals.id')
            ->orderBy('journal_details.id')
            ->select([
                'journals.id as journal_id',
                'journals.journal_number',
                'journals.journal_date',
                'journals.description as journal_description',
                'journal_details.description',
                'journal_details.debit',
                'journal_details.credit',
            ])
            ->get();

        $balance = round($opening, 2);
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        $entries = [];

        foreach ($rows as $row) {
            $debit = round((float) $row->debit, 2);
            $credit = round((float) $row->credit, 2);

            $balance = round($balance + $debit - $credit, 2);
            $debitTotal = round($debitTotal + $debit, 2);
            $creditTotal = round($creditTotal + $credit, 2);

            $entries[] = [
                'journal_id' => (int) $row->journal_id,
                'journal_number' => (string) $row->journal_number,
                'date' => (string) $row->journal_date,
                'description' => $row->description ?: $row->journal_description,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        }

        return [
            'account' => $account,
            'opening' => round($opening, 2),
            'rows' => $entries,
            'closing' => $balance,
            'debit' => $debitTotal,
            'credit' => $creditTotal,
        ];
    }

    /**
     * Ringkasan pendapatan, beban, dan surplus/defisit.
     *
     * @return array{revenue: float, expense: float, surplus: float}
     */
    public function profitLoss(?int $academicYearId = null, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $revenue = 0.0;
        $expense = 0.0;

        foreach ($this->trialBalance($academicYearId, $from, $to) as $row) {
            $balance = (float) $row->balance;

            if ($row->account->type === AccountType::Revenue) {
                $revenue = round($revenue - $balance, 2);
            } elseif ($row->account->type === AccountType::Expense) {
                $expense = round($expense + $balance, 2);
            }
        }

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'surplus' => round($revenue - $expense, 2),
        ];
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function resolveRange(?int $academicYearId, ?Carbon $from, ?Carbon $to): array
    {
        if ($academicYearId !== null && ($from === null || $to === null)) {
            $year = AcademicYear::query()->find($academicYearId);

            if ($year !== null) {
                $from ??= $year->start_date->copy();
                $to ??= $year->end_date->copy();
            }
        }

        return [$from, $to];
    }

    private function isDebitNormal(Account $account): bool
    {
        return $account->type !== AccountType::Revenue;
    }

    /**
     * @return Collection<int|string, object>
     */
    private function movements(?int $accountId, ?Carbon $from, ?Carbon $to): Collection
    {
        return DB::table('journal_details')
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->whereNull('journals.deleted_at')
            ->when($accountId !== null, fn ($query) => $query->where('journal_details.account_id', $accountId))
            ->when($from !== null, fn ($query) => $query->whereDate('journals.journal_date', '>=', $from->toDateString()))
            ->when($to !== null, fn ($query) => $query->whereDate('journals.journal_date', '<=', $to->toDateString()))
            ->selectRaw('journal_details.account_id, SUM(journal_details.debit) as debit, SUM(journal_details.credit) as credit')
            ->groupBy('journal_details.account_id')
            ->get()
            ->keyBy('account_id');
    }
}

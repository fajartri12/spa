<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JournalStatus;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Layanan Penyusunan dan Evaluasi RAPBM (Budgeting).
 */
final class BudgetService
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Simpan / perbarui anggaran per akun untuk tahun ajaran tertentu.
     *
     * @param  array<int, array{account_id: int, planned_amount: float, notes?: ?string}>  $items
     */
    public function saveBudget(int $academicYearId, array $items, User $actor): void
    {
        DB::transaction(function () use ($academicYearId, $items, $actor): void {
            foreach ($items as $item) {
                $planned = round((float) ($item['planned_amount'] ?? 0), 2);

                if ($planned <= 0) {
                    Budget::query()
                        ->where('academic_year_id', $academicYearId)
                        ->where('account_id', $item['account_id'])
                        ->delete();
                    continue;
                }

                Budget::query()->updateOrCreate(
                    [
                        'academic_year_id' => $academicYearId,
                        'account_id' => $item['account_id'],
                    ],
                    [
                        'planned_amount' => $planned,
                        'notes' => $item['notes'] ?? null,
                    ],
                );
            }

            $this->logger->log(
                'budget.updated',
                "Pembaruan anggaran RAPBM tahun ajaran ID {$academicYearId}",
                null,
                ['academic_year_id' => $academicYearId, 'count' => count($items)],
                $actor,
            );
        });
    }

    /**
     * Perbandingan RAPBM vs Realisasi (Posted Journals) per akun.
     *
     * @return Collection<int, object>
     */
    public function compare(int $academicYearId): Collection
    {
        $year = AcademicYear::query()->findOrFail($academicYearId);
        $budgets = Budget::query()
            ->where('academic_year_id', $academicYearId)
            ->get()
            ->keyBy('account_id');

        // Total realisasi dari jurnal posted dalam rentang tahun ajaran
        $actuals = DB::table('journal_details')
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.status', JournalStatus::Posted->value)
            ->whereNull('journals.deleted_at')
            ->whereDate('journals.journal_date', '>=', $year->start_date->toDateString())
            ->whereDate('journals.journal_date', '<=', $year->end_date->toDateString())
            ->selectRaw('journal_details.account_id, SUM(journal_details.debit) as total_debit, SUM(journal_details.credit) as total_credit')
            ->groupBy('journal_details.account_id')
            ->get()
            ->keyBy('account_id');

        return Account::query()->ordered()->get()->map(function (Account $account) use ($budgets, $actuals): object {
            $budget = $budgets[$account->id] ?? null;
            $planned = $budget !== null ? (float) $budget->planned_amount : 0.0;

            $actualRow = $actuals[$account->id] ?? null;
            $debit = round((float) ($actualRow->total_debit ?? 0), 2);
            $credit = round((float) ($actualRow->total_credit ?? 0), 2);

            // Untuk pendapatan (credit normal), realisasi = credit - debit.
            // Untuk beban/aset (debit normal), realisasi = debit - credit.
            $isRevenue = $account->type->value === 'revenue';
            $actual = $isRevenue ? round($credit - $debit, 2) : round($debit - $credit, 2);

            $variance = round($actual - $planned, 2);
            $percentage = $planned > 0 ? round(($actual / $planned) * 100, 1) : 0.0;

            return (object) [
                'account' => $account,
                'planned' => $planned,
                'actual' => $actual,
                'variance' => $variance,
                'percentage' => $percentage,
                'notes' => $budget?->notes,
            ];
        });
    }
}

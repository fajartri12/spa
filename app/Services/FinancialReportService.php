<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\StudentBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function approvedPayments(?string $from = null, ?string $to = null, ?int $academicYearId = null): Collection
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();

        return Payment::query()->with('student')->where('status', 'approved')
            ->whereBetween('paid_at', [$fromDate, $toDate])
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->orderBy('paid_at')->get();
    }

    public function outstandingBills(?int $academicYearId = null): Collection
    {
        return StudentBill::query()->with(['student', 'bill'])
            ->where('remaining_amount', '>', 0)
            ->when($academicYearId, fn ($query) => $query->whereHas('bill', fn ($bill) => $bill->where('academic_year_id', $academicYearId)))
            ->orderByDesc('remaining_amount')->get();
    }

    public function report(?string $from = null, ?string $to = null, ?int $academicYearId = null): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $toDate = $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay();
        $payments = Payment::query()->where('status', 'approved')->whereBetween('paid_at', [$fromDate, $toDate]);
        $cash = CashTransaction::query()->where('type', 'income')->whereBetween('transacted_at', [$fromDate, $toDate]);
        if ($academicYearId) {
            $payments->where('academic_year_id', $academicYearId);
            $cash->whereHas('payment', fn ($query) => $query->where('academic_year_id', $academicYearId));
        }
        $monthly = Payment::query()
            ->selectRaw($this->periodExpression().' as period, SUM(amount) as total')
            ->where('status', 'approved')->whereBetween('paid_at', [$fromDate, $toDate])
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->groupBy('period')->orderBy('period')->pluck('total', 'period');

        $outstanding = StudentBill::query()->where('remaining_amount', '>', 0)
            ->when($academicYearId, fn ($query) => $query->whereHas('bill', fn ($bill) => $bill->where('academic_year_id', $academicYearId)));

        $outstandingBills = StudentBill::query()->with(['student', 'bill'])->where('remaining_amount', '>', 0)
            ->when($academicYearId, fn ($query) => $query->whereHas('bill', fn ($bill) => $bill->where('academic_year_id', $academicYearId)))
            ->orderByDesc('remaining_amount')->limit(10)->get();

        return [
            'total_income' => (float) $payments->sum('amount'),
            'cash_income' => (float) $cash->sum('amount'),
            'outstanding' => (float) $outstanding->sum('remaining_amount'),
            'monthly' => $monthly,
            'recent' => $payments->with('student')->latest('paid_at')->limit(10)->get(),
            'outstanding_bills' => $outstandingBills,
        ];
    }

    private function periodExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', paid_at)",
            'pgsql' => "to_char(paid_at, 'YYYY-MM')",
            'sqlsrv' => "FORMAT(paid_at, 'yyyy-MM')",
            default => "DATE_FORMAT(paid_at, '%Y-%m')",
        };
    }
}

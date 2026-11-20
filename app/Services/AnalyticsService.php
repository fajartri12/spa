<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analitik piutang dan proyeksi penerimaan.
 *
 * Semua angka dihitung langsung dari tabel transaksi (tanpa tabel ringkasan)
 * agar tidak ada selisih yang perlu disinkronkan. Kueri dikelompokkan per
 * bucket memakai rentang tanggal yang portabel di MySQL maupun SQLite.
 */
final class AnalyticsService
{
    /**
     * Umur piutang berbasis tanggal jatuh tempo.
     *
     * @return array{buckets: array<int, array{label: string, amount: float, count: int}>, total: float, count: int}
     */
    public function aging(?int $academicYearId = null, ?int $unitId = null): array
    {
        $today = Carbon::today();

        $definitions = [
            ['label' => 'Belum jatuh tempo', 'from' => $today->toDateString(), 'to' => null],
            ['label' => '1 – 30 hari', 'from' => $today->copy()->subDays(30)->toDateString(), 'to' => $today->copy()->subDay()->toDateString()],
            ['label' => '31 – 60 hari', 'from' => $today->copy()->subDays(60)->toDateString(), 'to' => $today->copy()->subDays(31)->toDateString()],
            ['label' => '61 – 90 hari', 'from' => $today->copy()->subDays(90)->toDateString(), 'to' => $today->copy()->subDays(61)->toDateString()],
            ['label' => 'Lebih dari 90 hari', 'from' => null, 'to' => $today->copy()->subDays(91)->toDateString()],
        ];

        $buckets = [];
        $total = 0.0;
        $count = 0;

        foreach ($definitions as $definition) {
            $query = $this->openBills($academicYearId, $unitId);

            if ($definition['from'] !== null) {
                $query->where('student_bills.due_date', '>=', $definition['from']);
            }

            if ($definition['to'] !== null) {
                $query->where('student_bills.due_date', '<=', $definition['to']);
            }

            $row = $query
                ->selectRaw('COALESCE(SUM(student_bills.remaining_amount), 0) as amount, COUNT(*) as total')
                ->first();

            $amount = round((float) $row->amount, 2);
            $rowCount = (int) $row->total;

            $buckets[] = ['label' => $definition['label'], 'amount' => $amount, 'count' => $rowCount];
            $total = round($total + $amount, 2);
            $count += $rowCount;
        }

        return ['buckets' => $buckets, 'total' => $total, 'count' => $count];
    }

    /**
     * Rasio penagihan: total terbayar dibandingkan total tagihan terbit.
     *
     * @return array{billed: float, collected: float, rate: float, rows: array<int, array<string, mixed>>}
     */
    public function collectionRate(?int $academicYearId = null, ?int $unitId = null): array
    {
        $rows = DB::table('student_bills')
            ->join('bills', 'bills.id', '=', 'student_bills.bill_id')
            ->join('students', 'students.id', '=', 'student_bills.student_id')
            ->leftJoin('school_classes', 'school_classes.id', '=', 'students.school_class_id')
            ->where('student_bills.status', '!=', BillStatus::Cancelled->value)
            ->when($academicYearId !== null, fn (Builder $query) => $query->where('bills.academic_year_id', $academicYearId))
            ->when($unitId !== null, fn (Builder $query) => $query->where('students.unit_id', $unitId))
            ->selectRaw("COALESCE(school_classes.name, 'Tanpa Kelas') as class_name, COALESCE(SUM(student_bills.final_amount), 0) as billed, COALESCE(SUM(student_bills.paid_amount), 0) as collected")
            ->groupByRaw("COALESCE(school_classes.name, 'Tanpa Kelas')")
            ->orderBy('class_name')
            ->get()
            ->map(function ($row): array {
                $billed = round((float) $row->billed, 2);
                $collected = round((float) $row->collected, 2);

                return [
                    'class_name' => (string) $row->class_name,
                    'billed' => $billed,
                    'collected' => $collected,
                    'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : 0.0,
                ];
            })
            ->all();

        $billed = round(array_sum(array_column($rows, 'billed')), 2);
        $collected = round(array_sum(array_column($rows, 'collected')), 2);

        return [
            'billed' => $billed,
            'collected' => $collected,
            'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : 0.0,
            'rows' => $rows,
        ];
    }

    /**
     * Proyeksi penerimaan bulan berikutnya memakai rata-rata bergerak
     * tiga bulan terakhir dari pembayaran yang sudah disetujui.
     *
     * @return array{labels: array<int, string>, values: array<int, float>, projection: float, average: float}
     */
    public function forecast(int $months = 6, ?int $unitId = null): array
    {
        $start = Carbon::today()->startOfMonth()->subMonths(max(1, $months) - 1);
        $expression = $this->monthExpression();

        $rows = DB::table('payments')
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->selectRaw($expression.' as period, SUM(payments.amount) as total')
            ->where('payments.status', 'approved')
            ->whereNull('payments.deleted_at')
            ->where('payments.paid_at', '>=', $start->copy()->startOfDay())
            ->when($unitId !== null, fn (Builder $query) => $query->where('students.unit_id', $unitId))
            ->groupByRaw($expression)
            ->orderByRaw($expression)
            ->pluck('total', 'period');

        $labels = [];
        $values = [];
        $cursor = $start->copy();

        for ($index = 0; $index < max(1, $months); $index++) {
            $labels[] = $cursor->copy()->locale('id')->translatedFormat('M Y');
            $values[] = round((float) ($rows[$cursor->format('Y-m')] ?? 0), 2);
            $cursor->addMonth();
        }

        $recent = array_slice($values, -3);
        $average = $recent === [] ? 0.0 : round(array_sum($recent) / count($recent), 2);

        return [
            'labels' => $labels,
            'values' => $values,
            'projection' => $average,
            'average' => $average,
        ];
    }

    /**
     * @return Builder
     */
    private function openBills(?int $academicYearId, ?int $unitId): Builder
    {
        return DB::table('student_bills')
            ->join('bills', 'bills.id', '=', 'student_bills.bill_id')
            ->join('students', 'students.id', '=', 'student_bills.student_id')
            ->whereIn('student_bills.status', [BillStatus::Unpaid->value, BillStatus::Partial->value])
            ->where('student_bills.remaining_amount', '>', 0)
            ->when($academicYearId !== null, fn (Builder $query) => $query->where('bills.academic_year_id', $academicYearId))
            ->when($unitId !== null, fn (Builder $query) => $query->where('students.unit_id', $unitId));
    }

    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', payments.paid_at)",
            'pgsql' => "to_char(payments.paid_at, 'YYYY-MM')",
            'sqlsrv' => "FORMAT(payments.paid_at, 'yyyy-MM')",
            default => "DATE_FORMAT(payments.paid_at, '%Y-%m')",
        };
    }
}

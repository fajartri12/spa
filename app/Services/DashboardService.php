<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AppSetting;
use App\Models\StudentSavingsAccount;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agregasi data dashboard (DESIGN.MD bagian 5).
 *
 * Modul tagihan/pembayaran baru tersedia bertahap sesuai IMPLEMENTATION_PLAN.
 * Selama tabelnya belum ada, metrik terkait bernilai nol sehingga dashboard
 * tetap berfungsi tanpa data palsu dan otomatis hidup saat modul terpasang.
 *
 * Filter:
 * - $academicYearId menyaring lewat relasi siswa→kelas untuk metrik master, dan
 *   lewat kolom akademik langsung untuk metrik transaksi.
 * - $unitId menyaring lewat kolom unit pada master data dan lewat siswa pemilik
 *   transaksi (payments/tagihan tidak menyimpan unit_id).
 * - $from/$to hanya memengaruhi metrik berbasis transaksi.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function statistics(
        ?int $academicYearId = null,
        ?int $unitId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        $students = $this->countStudents($academicYearId, $unitId);
        $totalBills = $this->sumStudentBills($academicYearId, $unitId);
        $paid = $this->sumApprovedPayments($academicYearId, $unitId, $from, $to);
        $unpaid = max($totalBills - $paid, 0.0);
        $overdue = $this->overdueAmount($academicYearId, $unitId);

        $statistics = [
            'students' => $students,
            'total_bills' => $totalBills,
            'paid' => $paid,
            'unpaid' => $unpaid,
            'overdue' => $overdue,
            'units' => $this->count('units', null),
            'classes' => $this->countClasses($academicYearId, $unitId),
            'years' => $this->count('academic_years', null),
            'staff' => $this->countStaff($unitId),
            'students_account' => $this->countStudentsWithPortal($academicYearId, $unitId),
        ];

        if (AppSetting::get('feature_student_savings_enabled', '1') === '1') {
            $statistics['savings'] = $this->savingsSummary($academicYearId, $unitId);
        }

        return $statistics;
    }

    /**
     * Ringkasan rekening tabungan siswa.
     *
     * @return array{accounts: int, balance: float}
     */
    public function savingsSummary(?int $academicYearId = null, ?int $unitId = null): array
    {
        if (! Schema::hasTable('student_savings_accounts')) {
            return ['accounts' => 0, 'balance' => 0.0];
        }

        $query = StudentSavingsAccount::query();

        if ($academicYearId !== null || $unitId !== null) {
            $query->whereHas('student', function (EloquentBuilder $studentQuery) use ($academicYearId, $unitId): void {
                if ($unitId !== null) {
                    $studentQuery->where('unit_id', $unitId);
                }

                if ($academicYearId !== null) {
                    $studentQuery->whereHas(
                        'schoolClass',
                        fn (EloquentBuilder $classQuery) => $classQuery->where('academic_year_id', $academicYearId),
                    );
                }
            });
        }

        return [
            'accounts' => (clone $query)->count(),
            'balance' => round((float) $query->sum('balance'), 2),
        ];
    }

    /**
     * Pemasukan terverifikasi per bulan.
     *
     * Tanpa filter tanggal, rentang mengikuti tahun ajaran terpilih; bila tahun
     * ajaran juga kosong dipakai tahun kalender berjalan sehingga grafik selalu
     * punya dua belas titik.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    public function monthlyPayments(
        ?int $academicYearId = null,
        ?int $unitId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): array {
        [$start, $end] = $this->resolveRange($academicYearId, $from, $to);

        $rows = collect();

        if ($this->hasTable('payments') && $this->hasTable('students')) {
            $expression = $this->monthExpression();

            $rows = DB::table('payments')
                ->join('students', 'students.id', '=', 'payments.student_id')
                ->selectRaw($expression.' as period, SUM(payments.amount) as total')
                ->where('payments.status', 'approved')
                ->whereNull('payments.deleted_at')
                ->whereBetween('payments.paid_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->when($unitId !== null, fn ($q) => $q->where('students.unit_id', $unitId))
                ->groupByRaw($expression)
                ->orderByRaw($expression)
                ->pluck('total', 'period');
        }

        $labels = [];
        $values = [];

        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $labels[] = $cursor->copy()->locale('id')->translatedFormat('M');
            $values[] = round((float) ($rows[$cursor->format('Y-m')] ?? 0), 2);
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Komposisi status tagihan.
     *
     * @return array<string, float>
     */
    public function billingStatus(?int $academicYearId = null, ?int $unitId = null): array
    {
        $statuses = [
            'unpaid' => 0.0,
            'partial' => 0.0,
            'paid' => 0.0,
            'overdue' => 0.0,
        ];

        if (! $this->hasTable('student_bills') || ! $this->hasTable('bills')) {
            return $statuses;
        }

        $rows = DB::table('student_bills')
            ->join('bills', 'bills.id', '=', 'student_bills.bill_id')
            ->selectRaw('student_bills.status, COUNT(*) as total')
            ->whereNull('student_bills.deleted_at')
            ->when($academicYearId, fn ($q) => $q->where('bills.academic_year_id', $academicYearId))
            ->when($unitId !== null, fn ($q) => $q->whereIn('student_bills.student_id', $this->studentIdsForUnit($unitId)))
            ->groupBy('student_bills.status')
            ->pluck('total', 'status');

        foreach ($statuses as $key => $value) {
            $statuses[$key] = (float) ($rows[$key] ?? 0);
        }

        return $statuses;
    }

    /**
     * Distribusi pembayaran per kategori.
     *
     * @return Collection<int, object>
     */
    public function paymentsByCategory(
        ?int $academicYearId = null,
        int $limit = 6,
        ?int $unitId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): Collection {
        if (! $this->hasTable('payments') || ! $this->hasTable('payment_categories') || ! $this->hasTable('students')) {
            return collect();
        }

        return DB::table('payments')
            ->join('payment_categories', 'payment_categories.id', '=', 'payments.payment_category_id')
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->selectRaw('payment_categories.name as name, SUM(payments.amount) as total')
            ->where('payments.status', 'approved')
            ->whereNull('payments.deleted_at')
            ->whereNull('payment_categories.deleted_at')
            ->when($academicYearId, fn ($q) => $q->where('payments.academic_year_id', $academicYearId))
            ->when($unitId !== null, fn ($q) => $q->where('students.unit_id', $unitId))
            ->when($from !== null, fn ($q) => $q->where('payments.paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to !== null, fn ($q) => $q->where('payments.paid_at', '<=', $to->copy()->endOfDay()))
            ->groupBy('payment_categories.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * Transaksi terbaru (DESIGN.MD 5.4).
     *
     * @return Collection<int, object>
     */
    public function recentPayments(
        int $limit = 8,
        ?int $unitId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): Collection {
        if (! $this->hasTable('payments') || ! $this->hasTable('students')) {
            return collect();
        }

        return DB::table('payments')
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->select([
                'payments.id',
                'payments.transaction_number',
                'payments.paid_at',
                'payments.amount',
                'payments.status',
                'students.name as student_name',
            ])
            ->when($unitId !== null, fn ($q) => $q->where('students.unit_id', $unitId))
            ->when($from !== null, fn ($q) => $q->where('payments.paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to !== null, fn ($q) => $q->where('payments.paid_at', '<=', $to->copy()->endOfDay()))
            ->orderByDesc('payments.paid_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Pembayaran yang menunggu verifikasi.
     *
     * @return Collection<int, object>
     */
    public function pendingPayments(int $limit = 8, ?int $unitId = null): Collection
    {
        if (! $this->hasTable('payments') || ! $this->hasTable('students')) {
            return collect();
        }

        return DB::table('payments')
            ->join('students', 'students.id', '=', 'payments.student_id')
            ->select([
                'payments.id',
                'payments.transaction_number',
                'payments.paid_at',
                'payments.amount',
                'students.name as student_name',
            ])
            ->where('payments.status', 'pending')
            ->when($unitId !== null, fn ($q) => $q->where('students.unit_id', $unitId))
            ->orderBy('payments.paid_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    public function units(): Collection
    {
        return Unit::query()->active()->ordered()->get();
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    public function academicYears(): Collection
    {
        return AcademicYear::query()->orderByDesc('start_date')->get();
    }

    /**
     * Rentang tanggal efektif untuk metrik transaksi.
     *
     * Urutan prioritas: rentang eksplisit → tahun ajaran → tahun kalender berjalan.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveRange(?int $academicYearId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from !== null && $to !== null) {
            return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        }

        if ($academicYearId !== null) {
            $year = AcademicYear::query()->find($academicYearId);

            if ($year !== null && $year->start_date !== null && $year->end_date !== null) {
                return [
                    Carbon::parse($year->start_date)->startOfDay(),
                    Carbon::parse($year->end_date)->endOfDay(),
                ];
            }
        }

        return [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * Sub-query id siswa pada unit terpilih (tabel transaksi tidak menyimpan unit).
     *
     * @return Builder
     */
    private function studentIdsForUnit(int $unitId): Builder
    {
        return DB::table('students')
            ->select('id')
            ->where('unit_id', $unitId)
            ->whereNull('deleted_at');
    }

    private function count(string $table, ?int $academicYearId): int
    {
        if (! $this->hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)
            ->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when($academicYearId && Schema::hasColumn($table, 'academic_year_id'), fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->count();
    }

    private function countClasses(?int $academicYearId, ?int $unitId): int
    {
        if (! $this->hasTable('school_classes')) {
            return 0;
        }

        return (int) DB::table('school_classes')
            ->when(Schema::hasColumn('school_classes', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->count();
    }

    /**
     * Akun petugas = user aktif yang memiliki role.
     */
    private function countStaff(?int $unitId = null): int
    {
        if (! $this->hasTable('users') || ! $this->hasTable('model_has_roles')) {
            return 0;
        }

        return (int) DB::table('users')
            ->whereNull('users.deleted_at')
            ->where('users.status', 'active')
            ->when($unitId !== null && $this->hasTable('staff_profiles'), fn ($q) => $q->whereIn(
                'users.id',
                DB::table('staff_profiles')
                    ->select('user_id')
                    ->where('unit_id', $unitId)
                    ->whereNotNull('user_id')
                    ->whereNull('deleted_at'),
            ))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('model_has_roles')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', User::class))
            ->count();
    }

    /**
     * Siswa terhubung ke tahun ajaran melalui kelas, bukan kolom langsung.
     */
    private function countStudents(?int $academicYearId, ?int $unitId = null): int
    {
        if (! $this->hasTable('students')) {
            return 0;
        }

        $query = DB::table('students')->whereNull('students.deleted_at');

        if ($unitId !== null) {
            $query->where('students.unit_id', $unitId);
        }

        if ($academicYearId !== null) {
            $query->join('school_classes', 'school_classes.id', '=', 'students.school_class_id')
                ->where('school_classes.academic_year_id', $academicYearId);
        }

        return (int) $query->count();
    }

    /**
     * Siswa yang sudah memiliki kata sandi portal (akun portal aktif).
     */
    private function countStudentsWithPortal(?int $academicYearId, ?int $unitId = null): int
    {
        if (! $this->hasTable('students') || ! Schema::hasColumn('students', 'portal_password')) {
            return 0;
        }

        $query = DB::table('students')
            ->whereNull('students.deleted_at')
            ->whereNotNull('students.portal_password')
            ->where('students.portal_password', '!=', '');

        if ($unitId !== null) {
            $query->where('students.unit_id', $unitId);
        }

        if ($academicYearId !== null) {
            $query->join('school_classes', 'school_classes.id', '=', 'students.school_class_id')
                ->where('school_classes.academic_year_id', $academicYearId);
        }

        return (int) $query->count();
    }

    private function sumStudentBills(?int $academicYearId, ?int $unitId = null): float
    {
        if (! $this->hasTable('student_bills') || ! $this->hasTable('bills')) {
            return 0.0;
        }

        return (float) DB::table('student_bills')
            ->join('bills', 'bills.id', '=', 'student_bills.bill_id')
            ->whereNull('student_bills.deleted_at')
            ->when($academicYearId, fn ($q) => $q->where('bills.academic_year_id', $academicYearId))
            ->when($unitId !== null, fn ($q) => $q->whereIn('student_bills.student_id', $this->studentIdsForUnit($unitId)))
            ->sum('student_bills.final_amount');
    }

    private function sumApprovedPayments(
        ?int $academicYearId,
        ?int $unitId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): float {
        if (! $this->hasTable('payments')) {
            return 0.0;
        }

        return (float) DB::table('payments')
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->when($unitId !== null && $this->hasTable('students'), fn ($q) => $q->whereIn('student_id', $this->studentIdsForUnit($unitId)))
            ->when($from !== null, fn ($q) => $q->where('paid_at', '>=', $from->copy()->startOfDay()))
            ->when($to !== null, fn ($q) => $q->where('paid_at', '<=', $to->copy()->endOfDay()))
            ->sum('amount');
    }

    private function overdueAmount(?int $academicYearId, ?int $unitId = null): float
    {
        if (! $this->hasTable('student_bills') || ! $this->hasTable('bills')) {
            return 0.0;
        }

        return (float) DB::table('student_bills')
            ->join('bills', 'bills.id', '=', 'student_bills.bill_id')
            ->whereNull('student_bills.deleted_at')
            ->whereIn('student_bills.status', ['unpaid', 'partial', 'overdue'])
            ->whereDate('student_bills.due_date', '<=', Carbon::now()->endOfMonth())
            ->when($academicYearId, fn ($q) => $q->where('bills.academic_year_id', $academicYearId))
            ->when($unitId !== null, fn ($q) => $q->whereIn('student_bills.student_id', $this->studentIdsForUnit($unitId)))
            ->sum('student_bills.remaining_amount');
    }

    /**
     * Ekspresi pengelompokan bulan yang portabel antar driver.
     */
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

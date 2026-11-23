<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;

use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Matriks status pembayaran SPP bulanan per siswa.
 *
 * Menyajikan status 12 bulan (Januari - Desember atau periode tahun ajaran)
 * untuk membantu wali dan petugas memantau tunggakan SPP dalam satu pandangan.
 */
class SppMatrixService
{
    /**
     * @return array{
     *     student: Student,
     *     academic_year: AcademicYear|null,
     *     months: array<int, array{
     *         month_number: int,
     *         month_name: string,
     *         period: string,
     *         bill: StudentBill|null,
     *         status: string,
     *         amount: float,
     *         paid_amount: float,
     *         remaining_amount: float,
     *         due_date: Carbon|null,
     *     }>,
     *     summary: array{
     *         total_target: float,
     *         total_paid: float,
     *         total_remaining: float,
     *         paid_count: int,
     *         unpaid_count: int,
     *         bill_count: int,
     *     }
     * }
     */
    public function getMatrix(Student $student, ?int $academicYearId = null): array
    {
        $year = $academicYearId !== null
            ? AcademicYear::query()->find($academicYearId)
            : ($student->schoolClass?->academicYear ?? AcademicYear::current());

        $yearName = $year?->name ?? (string) now()->year;

        // Tentukan 12 bulan berdasarkan tahun ajaran atau tahun berjalan
        $startYear = $year?->start_date ? (int) $year->start_date->format('Y') : (int) now()->year;
        $startMonth = $year?->start_date ? (int) $year->start_date->format('m') : 1;

        // Cari kategori SPP (berdasarkan kode SPP atau tipe monthly)
        $sppCategory = PaymentCategory::query()
            ->where('code', 'SPP')
            ->orWhere('name', 'like', '%SPP%')
            ->first();

        $billsQuery = StudentBill::query()
            ->where('student_id', $student->id)
            ->with(['bill.paymentCategory', 'bill.academicYear']);

        if ($sppCategory !== null) {
            $billsQuery->whereHas('bill', fn ($q) => $q->where('payment_category_id', $sppCategory->id));
        } else {
            $billsQuery->whereHas('bill.paymentCategory', fn ($q) => $q->where('type', 'monthly'));
        }

        if ($year !== null) {
            $billsQuery->whereHas('bill', fn ($q) => $q->where('academic_year_id', $year->id));
        }

        /** @var Collection<int, StudentBill> $studentBills */
        $studentBills = $billsQuery->get();

        // Map tagihan berdasarkan periode Y-m atau urutan bulan
        $billsByPeriod = $studentBills->keyBy(fn (StudentBill $sb) => $sb->bill?->period);

        $months = [];
        $totalTarget = 0.0;
        $totalPaid = 0.0;
        $totalRemaining = 0.0;
        $paidCount = 0;
        $unpaidCount = 0;
        $billCount = 0;

        // Buat 12 bulan berurutan
        for ($i = 0; $i < 12; $i++) {
            $date = Carbon::createFromDate($startYear, $startMonth, 1)->addMonths($i);
            $period = $date->format('Y-m');
            $monthName = $date->translatedFormat('F Y');

            /** @var StudentBill|null $bill */
            $bill = $billsByPeriod->get($period);

            if ($bill === null) {
                // Cari alternatif pencocokan bila periode disimpan tanpa format Y-m persis
                $bill = $studentBills->first(fn (StudentBill $sb) => $sb->due_date && $sb->due_date->format('Y-m') === $period);
            }

            $status = 'no_bill';
            $amount = 0.0;
            $paidAmount = 0.0;
            $remainingAmount = 0.0;
            $dueDate = null;

            if ($bill !== null) {
                $billCount++;
                $amount = (float) $bill->final_amount;
                $paidAmount = (float) $bill->paid_amount;
                $remainingAmount = (float) $bill->remaining_amount;
                $dueDate = $bill->due_date;

                $billStatusVal = $bill->status instanceof BillStatus ? $bill->status->value : (string) $bill->status;

                if ($billStatusVal === BillStatus::Paid->value || $remainingAmount <= 0) {
                    $status = 'paid';
                    $paidCount++;
                } elseif ($billStatusVal === BillStatus::Cancelled->value) {
                    $status = 'cancelled';
                } elseif ($paidAmount > 0) {
                    $status = 'partial';
                    $unpaidCount++;
                } elseif ($dueDate && $dueDate->isPast()) {
                    $status = 'overdue';
                    $unpaidCount++;
                } else {
                    $status = 'unpaid';
                    $unpaidCount++;
                }

                $totalTarget += $amount;
                $totalPaid += $paidAmount;
                $totalRemaining += $remainingAmount;
            }

            $months[] = [
                'month_number' => $i + 1,
                'month_name' => $monthName,
                'period' => $period,
                'bill' => $bill,
                'status' => $status,
                'amount' => $amount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'due_date' => $dueDate,
            ];
        }

        return [
            'student' => $student,
            'academic_year' => $year,
            'months' => $months,
            'summary' => [
                'total_target' => $totalTarget,
                'total_paid' => $totalPaid,
                'total_remaining' => $totalRemaining,
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount,
                'bill_count' => $billCount,
            ],
        ];
    }
}

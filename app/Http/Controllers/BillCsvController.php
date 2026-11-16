<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StudentBill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillCsvController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bills.view'), 403);

        $status = $request->filled('status') ? (string) $request->string('status') : null;
        $unit = $request->filled('unit') ? (int) $request->string('unit')->toString() : null;
        $class = $request->filled('kelas') ? (int) $request->string('kelas')->toString() : null;
        $year = $request->filled('tahun-ajaran') ? (int) $request->string('tahun-ajaran')->toString() : null;
        $category = $request->filled('kategori') ? (int) $request->string('kategori')->toString() : null;
        $period = $request->filled('periode') ? (string) $request->string('periode') : null;

        return response()->streamDownload(function () use ($status, $unit, $class, $year, $category, $period): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'bill_number', 'student_nis', 'student_name', 'unit', 'class',
                'category', 'academic_year', 'period', 'due_date',
                'original_amount', 'discount_amount', 'final_amount', 'paid_amount', 'remaining_amount', 'status',
            ], escape: '');

            $query = StudentBill::query()
                ->with(['bill.paymentCategory', 'bill.academicYear', 'student.unit', 'student.schoolClass'])
                ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
                ->when($unit !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $student) => $student->where('unit_id', $unit)))
                ->when($class !== null, fn (Builder $q) => $q->whereHas('student', fn (Builder $student) => $student->where('school_class_id', $class)))
                ->when($year !== null, fn (Builder $q) => $q->whereHas('bill', fn (Builder $bill) => $bill->where('academic_year_id', $year)))
                ->when($category !== null, fn (Builder $q) => $q->whereHas('bill', fn (Builder $bill) => $bill->where('payment_category_id', $category)))
                ->when($period !== null, fn (Builder $q) => $q->whereHas('bill', fn (Builder $bill) => $bill->where('period', $period)))
                ->latest('id');

            foreach ($query->lazy() as $studentBill) {
                fputcsv($output, array_map(self::spreadsheetSafe(...), [
                    $studentBill->bill_number,
                    $studentBill->student?->nis,
                    $studentBill->student?->name,
                    $studentBill->student?->unit?->name,
                    $studentBill->student?->schoolClass?->name,
                    $studentBill->bill?->paymentCategory?->name,
                    $studentBill->bill?->academicYear?->name,
                    $studentBill->bill?->period,
                    $studentBill->due_date?->toDateString(),
                    $studentBill->original_amount,
                    $studentBill->discount_amount,
                    $studentBill->final_amount,
                    $studentBill->paid_amount,
                    $studentBill->remaining_amount,
                    $studentBill->status?->value,
                ]), escape: '');
            }

            fclose($output);
        }, 'tagihan-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

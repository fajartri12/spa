<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CashTransactionType;
use App\Models\AcademicYear;
use App\Models\AppSetting;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\StudentBill;
use App\Models\StudentSavingsTransaction;
use App\Services\FinancialReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ReportPrintController extends Controller
{
    public function __invoke(Request $request, FinancialReportService $reports): Response
    {
        abort_unless($request->user()?->can('reports.export'), 403);

        // UI mengirim 'all' untuk filter kosong; samakan menjadi null sebelum validasi.
        $request->merge(array_map(
            static fn ($value): mixed => $value === 'all' || $value === '' ? null : $value,
            $request->only(['unit_id', 'payment_category_id', 'status', 'academic_year_id']),
        ));

        $filters = $request->validate([
            'type' => ['required', 'in:approved_payments,outstanding_bills,cash_flow,savings'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'payment_category_id' => ['nullable', 'integer', 'exists:payment_categories,id'],
            'status' => ['nullable', 'in:paid,unpaid,partial'],
        ]);

        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();
        $yearId = isset($filters['academic_year_id']) ? (int) $filters['academic_year_id'] : null;
        $type = $filters['type'];
        $unitId = $filters['unit_id'] ?? null;
        $categoryId = $filters['payment_category_id'] ?? null;
        $status = $filters['status'] ?? null;

        if ($type === 'outstanding_bills') {
            $query = StudentBill::query()->with(['student.unit', 'bill.paymentCategory']);

            if ($yearId) {
                $query->whereHas('bill', fn ($b) => $b->where('academic_year_id', $yearId));
            }
            if ($unitId && $unitId !== 'all') {
                $query->whereHas('student', fn ($s) => $s->where('unit_id', $unitId));
            }
            if ($categoryId && $categoryId !== 'all') {
                $query->whereHas('bill', fn ($b) => $b->where('payment_category_id', $categoryId));
            }
            if ($status && $status !== 'all') {
                if ($status === 'paid') {
                    $query->where('remaining_amount', '<=', 0);
                } elseif ($status === 'unpaid') {
                    $query->where('remaining_amount', '>', 0)->whereColumn('remaining_amount', 'final_amount');
                } elseif ($status === 'partial') {
                    $query->where('remaining_amount', '>', 0)->whereColumn('remaining_amount', '<', 'final_amount');
                }
            } else {
                $query->where('remaining_amount', '>', 0);
            }

            $bills = $query->orderByDesc('remaining_amount')->get();
            $title = 'Laporan Tunggakan Tagihan';
            $columns = ['No. Tagihan', 'Siswa', 'Tagihan', 'Jatuh Tempo', 'Sisa'];
            $rows = $bills->map(fn ($bill): array => [
                $bill->bill_number,
                $bill->student?->name ?? '-',
                $bill->bill?->title ?? '-',
                $bill->due_date?->format('d/m/Y') ?? '-',
                number_format((float) $bill->remaining_amount, 0, ',', '.'),
            ])->all();
            $total = (float) $bills->sum('remaining_amount');
        } elseif ($type === 'cash_flow') {
            $cash = CashTransaction::query()->with('account')
                ->whereIn('type', [CashTransactionType::Income, CashTransactionType::Expense])
                ->whereDate('transacted_at', '>=', $from)
                ->whereDate('transacted_at', '<=', $to)
                ->orderBy('transacted_at')->get();
            $title = 'Laporan Arus Kas';
            $columns = ['No. Transaksi', 'Tanggal', 'Akun', 'Keterangan', 'Jenis', 'Nominal'];
            $rows = $cash->map(fn (CashTransaction $row): array => [
                $row->transaction_number,
                $row->transacted_at?->format('d/m/Y') ?? '-',
                $row->account?->name ?? '-',
                $row->description,
                $row->type === CashTransactionType::Expense ? 'Pengeluaran' : 'Pemasukan',
                ($row->type === CashTransactionType::Expense ? '-' : '') . number_format((float) $row->amount, 0, ',', '.'),
            ])->all();
            $total = null;
        } elseif ($type === 'savings') {
            $query = StudentSavingsTransaction::query()->with(['account.student.unit', 'user'])
                ->whereDate('transacted_at', '>=', $from)
                ->whereDate('transacted_at', '<=', $to);

            if ($unitId && $unitId !== 'all') {
                $query->whereHas('account.student', fn ($s) => $s->where('unit_id', $unitId));
            }

            $savings = $query->orderBy('transacted_at')->get();
            $title = 'Laporan Tabungan Siswa';
            $columns = ['No. Transaksi', 'Tanggal', 'Siswa', 'Jenis', 'Nominal', 'Saldo Akhir'];
            $rows = $savings->map(fn (StudentSavingsTransaction $row): array => [
                $row->transaction_number,
                $row->transacted_at?->format('d/m/Y') ?? '-',
                $row->account?->student?->name ?? '-',
                $row->type === 'deposit' ? 'Setor' : 'Tarik',
                number_format((float) $row->amount, 0, ',', '.'),
                number_format((float) $row->balance_after, 0, ',', '.'),
            ])->all();
            $total = (float) $savings->where('type', 'deposit')->sum('amount') - (float) $savings->where('type', 'withdrawal')->sum('amount');
        } else {
            $query = Payment::query()->with(['student.unit', 'paymentCategory'])
                ->where('status', 'approved')
                ->whereBetween('paid_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()]);

            if ($yearId) {
                $query->where('academic_year_id', $yearId);
            }
            if ($unitId && $unitId !== 'all') {
                $query->whereHas('student', fn ($s) => $s->where('unit_id', $unitId));
            }
            if ($categoryId && $categoryId !== 'all') {
                $query->where('payment_category_id', $categoryId);
            }

            $payments = $query->orderBy('paid_at')->get();
            $title = 'Laporan Penerimaan Pembayaran';
            $columns = ['No. Transaksi', 'Tanggal', 'Siswa', 'Kategori', 'Nominal'];
            $rows = $payments->map(fn ($payment): array => [
                $payment->transaction_number,
                $payment->paid_at?->format('d/m/Y') ?? '-',
                $payment->student?->name ?? '-',
                $payment->paymentCategory?->name ?? '-',
                number_format((float) $payment->amount, 0, ',', '.'),
            ])->all();
            $total = (float) $payments->sum('amount');
        }

        $pdf = Pdf::loadView('reports.print', [
            'title' => $title,
            'appName' => AppSetting::get('app_name', config('app.name')),
            'appAddress' => AppSetting::get('school_address', ''),
            'columns' => $columns,
            'rows' => $rows,
            'total' => $total,
            'period' => $type === 'outstanding_bills'
                ? 'Per ' . Carbon::parse($to)->format('d/m/Y')
                : Carbon::parse($from)->format('d/m/Y') . ' s.d. ' . Carbon::parse($to)->format('d/m/Y'),
            'academicYear' => $yearId ? AcademicYear::query()->find($yearId)?->name : null,
            'printedBy' => $request->user()?->name,
            'headmasterName' => AppSetting::get('headmaster_name', '..........................'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($type . '-' . now()->format('YmdHis') . '.pdf');
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Cetak Laporan')]
class ReportPrintIndex extends Component
{
    public string $category = 'tagihan';
    public string $type = 'outstanding_bills';
    public ?int $academicYearId = null;
    public string $fromMonth = '';
    public string $fromYear = '';
    public string $toMonth = '';
    public string $toYear = '';
    public string $rekapType = 'daftar_tagihan';
    public string $billStatus = 'all';
    public string $categoryId = 'all';
    public string $unitId = 'all';

    public function mount(): void
    {
        $this->authorize('reports.export');
        $now = now();
        $this->fromMonth = $now->format('m');
        $this->fromYear = $now->format('Y');
        $this->toMonth = $now->format('m');
        $this->toYear = $now->format('Y');
        $this->academicYearId = AcademicYear::current()?->id;
    }

    public function updatedCategory(string $value): void
    {
        $this->type = match ($value) {
            'tagihan' => 'outstanding_bills',
            'arus_kas' => 'cash_flow',
            'tabungan' => 'savings',
            default => 'approved_payments',
        };
        $this->rekapType = $value === 'tagihan' ? 'daftar_tagihan' : 'rekap_transaksi';
    }

    public function render(): View
    {
        $this->authorize('reports.export');
        $from = Carbon::createFromDate((int) $this->fromYear, (int) $this->fromMonth, 1)->startOfMonth()->toDateString();
        $to = Carbon::createFromDate((int) $this->toYear, (int) $this->toMonth, 1)->endOfMonth()->toDateString();

        return view('livewire.reports.report-print-index', [
                'reportTitle' => match ($this->category) {
                    'tagihan' => 'Laporan Tunggakan Tagihan',
                    'arus_kas' => 'Laporan Arus Kas',
                    'tabungan' => 'Laporan Tabungan Siswa',
                    default => 'Laporan Penerimaan Pembayaran',
                },
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'paymentCategories' => PaymentCategory::active()->orderBy('name')->get(),
            'units' => Unit::active()->orderBy('name')->get(),
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $month): array => [str_pad((string) $month, 2, '0', STR_PAD_LEFT) => Carbon::create()->month($month)->translatedFormat('F')]),
            'years' => collect(range((int) now()->year - 5, (int) now()->year + 1))->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year]),
            'printUrl' => route('reports.print.download', array_filter([
                'type' => $this->type, 'from' => $from, 'to' => $to,
                'academic_year_id' => $this->academicYearId,
                'unit_id' => $this->unitId === 'all' ? null : $this->unitId,
                'payment_category_id' => $this->categoryId === 'all' ? null : $this->categoryId,
                'status' => $this->billStatus === 'all' ? null : $this->billStatus,
            ], static fn ($value): bool => $value !== null && $value !== '')),
        ]);
    }
}

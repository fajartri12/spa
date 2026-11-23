{{--
    Kartu visual matriks SPP (12 bulan).

    Parameter:
    - $sppMatrix : array dari App\Services\SppMatrixService::getMatrix()
    - $sppPdfUrl : URL unduh rekap PDF tahunan
--}}
@php
    $statusStyles = [
        'paid' => ['label' => 'Lunas', 'cell' => 'bg-[#DCFCE7] text-[#15803D] border-[#86EFAC]', 'dot' => 'bg-[#16A34A]'],
        'partial' => ['label' => 'Sebagian', 'cell' => 'bg-[#DBEAFE] text-[#1D4ED8] border-[#93C5FD]', 'dot' => 'bg-[#2563EB]'],
        'overdue' => ['label' => 'Jatuh Tempo', 'cell' => 'bg-[#FEE2E2] text-[#B91C1C] border-[#FCA5A5]', 'dot' => 'bg-[#DC2626]'],
        'unpaid' => ['label' => 'Belum Bayar', 'cell' => 'bg-[#FEF3C7] text-[#B45309] border-[#FCD34D]', 'dot' => 'bg-[#D97706]'],
        'cancelled' => ['label' => 'Dibatalkan', 'cell' => 'bg-slate-100 text-slate-500 border-slate-300', 'dot' => 'bg-slate-400'],
        'no_bill' => ['label' => 'Tanpa Tagihan', 'cell' => 'bg-[#F8FAFC] text-[#94A3B8] border-dashed border-[#E2E8F0]', 'dot' => 'bg-[#CBD5E1]'],
    ];

    $summary = $sppMatrix['summary'];
    $progress = $summary['total_target'] > 0
        ? min(100, round($summary['total_paid'] / $summary['total_target'] * 100))
        : 0;
@endphp

<section id="spp" data-portal-section aria-label="Matriks pembayaran SPP" class="portal-card scroll-mt-24 overflow-hidden">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[#E2E8F0] px-4 py-4 sm:px-5">
        <div class="min-w-0">
            <h2 class="text-base font-bold text-[#0F172A] sm:text-lg">Kartu SPP Tahunan</h2>
            <p class="mt-0.5 text-xs text-[#64748B]">
                Status pembayaran per bulan &middot; {{ $sppMatrix['academic_year']?->name ?? now()->year }}
            </p>
        </div>
        <a href="{{ $sppPdfUrl }}"
           class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl bg-[#2563EB] px-3.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1D4ED8] focus:outline-none focus:ring-2 focus:ring-[#2563EB]">
            <i data-lucide="file-down" aria-hidden="true" class="h-4 w-4"></i>
            Cetak Rekap SPP (PDF)
        </a>
    </div>

    <div class="p-4 sm:p-5">
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($sppMatrix['months'] as $month)
                @php $style = $statusStyles[$month['status']] ?? $statusStyles['no_bill']; @endphp
                <div class="rounded-[14px] border p-3 {{ $style['cell'] }}"
                     title="{{ $style['label'] }}{{ $month['bill'] ? ' · Rp ' . number_format($month['amount'], 0, ',', '.') : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-semibold uppercase tracking-wide">{{ \Illuminate\Support\Str::before($month['month_name'], ' ') }}</span>
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $style['dot'] }}" aria-hidden="true"></span>
                    </div>
                    <p class="mt-1 text-sm font-bold tabular-nums">
                        @if($month['status'] === 'no_bill')
                            &mdash;
                        @else
                            Rp {{ number_format($month['remaining_amount'], 0, ',', '.') }}
                        @endif
                    </p>
                    <p class="mt-0.5 text-[10px] font-medium">{{ $style['label'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-[14px] bg-[#F8FAFC] p-4">
            <div class="flex items-center justify-between text-xs font-semibold text-[#334155]">
                <span>Progres Pembayaran</span>
                <span class="tabular-nums">{{ $progress }}%</span>
            </div>
            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-[#E2E8F0]" role="progressbar"
                 aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres pembayaran SPP">
                <div class="h-full rounded-full bg-[#2563EB]" style="width: {{ $progress }}%"></div>
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-[#64748B]">Total Tagihan</dt>
                    <dd class="font-semibold tabular-nums text-[#0F172A]">Rp {{ number_format($summary['total_target'], 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-[#64748B]">Sudah Dibayar</dt>
                    <dd class="font-semibold tabular-nums text-[#15803D]">Rp {{ number_format($summary['total_paid'], 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-[#64748B]">Sisa</dt>
                    <dd class="font-semibold tabular-nums text-[#DC2626]">Rp {{ number_format($summary['total_remaining'], 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-[#64748B]">Lunas</dt>
                    <dd class="font-semibold tabular-nums text-[#334155]">{{ $summary['paid_count'] }} / {{ $summary['bill_count'] }} bulan</dd>
                </div>
            </dl>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-[11px] text-[#64748B]">
            @foreach(['paid', 'partial', 'overdue', 'unpaid', 'cancelled'] as $key)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-full {{ $statusStyles[$key]['dot'] }}" aria-hidden="true"></span>
                    {{ $statusStyles[$key]['label'] }}
                </span>
            @endforeach
        </div>
    </div>
</section>
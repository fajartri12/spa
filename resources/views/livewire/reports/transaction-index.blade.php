@php use App\Support\Formatter; @endphp
@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Rekap', 'url' => route('reports.index')], ['label' => 'Transaksi']]" />@endsection
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Rekap Transaksi</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Daftar seluruh transaksi pembayaran pada rentang tanggal terpilih.</p>
        </div>
        @can('reports.export')
            <a href="{{ route('reports.print.download', ['type' => 'approved_payments', 'from' => $dateFrom, 'to' => $dateTo, 'academic_year_id' => $academicYearId]) }}" class="btn btn-secondary" target="_blank" rel="noopener">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Cetak Laporan</span>
            </a>
        @endcan
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Nomor transaksi atau siswa..." icon="search" />
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan']" />
            <x-ui.input wire:model.live="dateFrom" name="dateFrom" type="date" label="Dari" />
            <x-ui.input wire:model.live="dateTo" name="dateTo" type="date" label="Sampai" />
            <x-ui.select wire:model.live="academicYearId" name="academicYearId" label="Tahun Ajaran" :options="$academicYears->pluck('name', 'id')->all()" placeholder="Semua Tahun Ajaran" />
        </div>
    </div>

    @php
        $cards = [
            ['label' => 'Total Transaksi', 'amount' => $summary['total_amount'], 'count' => $summary['total_count'], 'color' => 'text-[#111827]'],
            ['label' => 'Transaksi Tagihan', 'amount' => $summary['bill_amount'], 'count' => $summary['bill_count'], 'color' => 'text-[#438a21]'],
            ['label' => 'Transaksi Lainnya', 'amount' => $summary['other_amount'], 'count' => $summary['other_count'], 'color' => 'text-[#6b46c1]'],
            ['label' => 'Transaksi Tunai', 'amount' => $summary['cash_amount'], 'count' => $summary['cash_count'], 'color' => 'text-[#2872c8]'],
            ['label' => 'Transaksi Non-Tunai', 'amount' => $summary['non_cash_amount'], 'count' => $summary['non_cash_count'], 'color' => 'text-[#b9770e]'],
        ];
    @endphp

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach($cards as $card)
            <x-ui.card>
                <p class="text-[12px] font-medium uppercase tracking-wide text-[#8994a3]">{{ $card['label'] }}</p>
                <p class="mt-2 text-lg font-semibold tabular {{ $card['color'] }}">{{ Formatter::rupiah($card['amount']) }}</p>
                <p class="mt-0.5 text-[12px] text-[#8994a3]">{{ number_format($card['count'], 0, ',', '.') }} transaksi disetujui</p>
            </x-ui.card>
        @endforeach
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$payments->isEmpty()" empty-title="Belum Ada Transaksi" empty-description="Tidak ada transaksi pada filter yang dipilih.">
            <x-slot:head>
                <tr>
                    <th>No. Induk</th>
                    <th>Nama Lengkap</th>
                    <th>Kategori</th>
                    <th>Metode Pembayaran</th>
                    <th>Waktu Pembayaran</th>
                    <th>Status</th>
                    <th class="text-right">Nominal</th>
                </tr>
            </x-slot:head>
            @foreach($payments as $payment)
                <tr wire:key="report-trx-{{ $payment->id }}">
                    <td class="font-medium tabular">{{ $payment->student?->nis ?: '-' }}</td>
                    <td>
                        <a href="{{ route('payments.show', $payment) }}" class="font-medium text-[#2872c8] hover:underline">{{ $payment->student?->name ?? '-' }}</a>
                        <div class="text-xs text-[#6b7280]">{{ $payment->transaction_number }}</div>
                    </td>
                    <td>{{ $payment->paymentCategory?->name ?? '-' }}</td>
                    <td>{{ $payment->details->pluck('paymentMethod.name')->filter()->unique()->implode(', ') ?: '-' }}</td>
                    <td>{{ $payment->paid_at?->format('d/m/Y H:i') ?? '-' }}</td>
                    <td>
                        <x-ui.badge :variant="match($payment->status->value) { 'approved' => 'success', 'rejected', 'cancelled' => 'danger', default => 'warning' }">
                            {{ match($payment->status->value) { 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', default => 'Menunggu' } }}
                        </x-ui.badge>
                    </td>
                    <td class="text-right tabular">{{ Formatter::rupiah((float) $payment->amount) }}</td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$payments" label="transaksi" />
    </div>
</div>

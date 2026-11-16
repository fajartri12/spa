@php use App\Support\Formatter; @endphp
@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Rekap', 'url' => route('reports.index')], ['label' => 'Ringkasan']]" />@endsection
<div class="space-y-5">
    <div>
        <h1 class="page-title">Laporan Keuangan</h1>
        <p class="text-[13px] text-[#64748b] mt-1">Ringkasan pemasukan, arus kas, dan tunggakan siswa.</p>
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-3">
            <x-ui.select wire:model.live="academicYearId" name="academicYearId" label="Tahun Ajaran" :options="$academicYears->pluck('name', 'id')->all()" placeholder="Semua Tahun Ajaran" />
            <x-ui.input wire:model.live="dateFrom" name="dateFrom" type="date" label="Dari" />
            <x-ui.input wire:model.live="dateTo" name="dateTo" type="date" label="Sampai" />
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <x-ui.card title="Pemasukan Terverifikasi">
            <p class="text-xl font-semibold tabular text-[#111827]">{{ Formatter::rupiah($report['total_income']) }}</p>
        </x-ui.card>
        <x-ui.card title="Pemasukan Kas">
            <p class="text-xl font-semibold tabular text-[#111827]">{{ Formatter::rupiah($report['cash_income']) }}</p>
        </x-ui.card>
        <x-ui.card title="Total Tunggakan">
            <p class="text-xl font-semibold tabular text-[#D83E78]">{{ Formatter::rupiah($report['outstanding']) }}</p>
        </x-ui.card>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <x-ui.card title="Transaksi Terbaru" subtitle="Pembayaran terverifikasi pada rentang tanggal">
            @can('reports.export')
                <x-slot:action>
                    <a href="{{ route('reports.export', ['type' => 'approved_payments', 'from' => $dateFrom, 'to' => $dateTo, 'academic_year_id' => $academicYearId]) }}" class="btn btn-secondary btn-sm">Ekspor CSV</a>
                </x-slot:action>
            @endcan
            <x-ui.table :empty="$report['recent']->isEmpty()" empty-title="Belum Ada Transaksi" empty-description="Tidak ada pembayaran terverifikasi pada rentang ini.">
                <x-slot:head>
                    <tr>
                        <th>Transaksi</th>
                        <th>Siswa</th>
                        <th>Tanggal</th>
                        <th class="text-right">Nominal</th>
                    </tr>
                </x-slot:head>
                @foreach($report['recent'] as $payment)
                    <tr wire:key="report-payment-{{ $payment->id }}">
                        <td class="font-medium text-[#111827]">{{ $payment->transaction_number }}</td>
                        <td>{{ $payment->student?->name ?? '-' }}</td>
                        <td>{{ $payment->paid_at?->format('d/m/Y') }}</td>
                        <td class="text-right tabular">{{ Formatter::rupiah((float) $payment->amount) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>

        <x-ui.card title="Tagihan Belum Lunas" subtitle="Sisa tagihan terbesar">
            @can('reports.export')
                <x-slot:action>
                    <a href="{{ route('reports.export', ['type' => 'outstanding_bills', 'academic_year_id' => $academicYearId]) }}" class="btn btn-secondary btn-sm">Ekspor CSV</a>
                </x-slot:action>
            @endcan
            <x-ui.table :empty="$report['outstanding_bills']->isEmpty()" empty-title="Tidak Ada Tunggakan" empty-description="Semua tagihan siswa sudah lunas.">
                <x-slot:head>
                    <tr>
                        <th>Siswa</th>
                        <th>Tagihan</th>
                        <th class="text-right">Sisa</th>
                    </tr>
                </x-slot:head>
                @foreach($report['outstanding_bills'] as $bill)
                    <tr wire:key="report-bill-{{ $bill->id }}">
                        <td class="font-medium text-[#111827]">{{ $bill->student?->name ?? '-' }}</td>
                        <td>{{ $bill->bill_number }}</td>
                        <td class="text-right tabular">{{ Formatter::rupiah((float) $bill->remaining_amount) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
    </div>
</div>

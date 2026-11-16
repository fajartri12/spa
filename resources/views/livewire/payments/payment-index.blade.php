@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Pembayaran']]" />@endsection
<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pembayaran</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Catat dan pantau pembayaran tagihan siswa.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('payments.verify')
                <a href="{{ route('payments.verification') }}" class="btn btn-secondary">
                    <i data-lucide="badge-check" class="w-4 h-4"></i>
                    <span>Verifikasi Pembayaran</span>
                </a>
            @endcan
            @can('payments.create')
                <a href="{{ route('payments.create') }}" class="btn btn-primary">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Catat Pembayaran</span>
                </a>
            @endcan
        </div>
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2">
            <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari Pembayaran" placeholder="Nomor transaksi atau nama siswa..." icon="search" />
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="['pending' => 'Menunggu Verifikasi', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan']" />
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$payments->isEmpty()" empty-title="Belum Ada Pembayaran" empty-description="Pembayaran yang dicatat akan tampil di sini.">
            <x-slot:head>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Siswa</th>
                    <th>Tanggal</th>
                    <th class="text-right">Nominal</th>
                    <th>Status</th>
                </tr>
            </x-slot:head>
            @foreach($payments as $payment)
                <tr wire:key="payment-{{ $payment->id }}">
                    <td class="font-medium text-[#111827]"><a href="{{ route('payments.show', $payment) }}" class="text-[#2872c8] hover:underline">{{ $payment->transaction_number }}</a></td>
                    <td>{{ $payment->student?->name ?? '-' }}</td>
                    <td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                    <td class="text-right tabular">{{ \App\Support\Formatter::rupiah((float) $payment->amount) }}</td>
                    <td>
                        <x-ui.badge :variant="match($payment->status->value) { 'approved' => 'success', 'rejected', 'cancelled' => 'danger', default => 'warning' }">
                            {{ match($payment->status->value) { 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan', default => 'Menunggu' } }}
                        </x-ui.badge>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$payments" label="pembayaran" />
    </div>
</div>

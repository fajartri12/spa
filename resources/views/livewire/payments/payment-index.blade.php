@section('breadcrumb')<x-ui.breadcrumb :items="[['label' => 'Pembayaran']]" />@endsection
<div class="space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="page-title">Daftar Pembayaran</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Pantau dan kelola riwayat transaksi pembayaran tagihan siswa.</p>
        </div>
        <div class="flex flex-wrap gap-2.5">
            @can('payments.verify')
                <a href="{{ route('payments.verification') }}" class="btn btn-secondary">
                    <i data-lucide="badge-check" class="w-4 h-4 text-[#2872c8]"></i>
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

    {{-- Filter & Search Card --}}
    <div class="card p-4 bg-white shadow-sm border border-[#e2e8f0] rounded-xl">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 items-end">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Pencarian Transaksi"
                    placeholder="Cari nomor transaksi, NIS, atau nama siswa..."
                    icon="search"
                />
            </div>
            <div>
                <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Filter Status">
                    <option value="">-- Semua Status --</option>
                    <option value="pending">Menunggu Verifikasi</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="cancelled">Dibatalkan</option>
                </x-ui.select>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="card !p-0 overflow-hidden shadow-sm border border-[#e2e8f0] rounded-xl bg-white" wire:loading.class="opacity-60">
        <x-ui.table :empty="$payments->isEmpty()" empty-title="Belum Ada Pembayaran" empty-description="Pembayaran yang dicatat atau masuk lewat payment gateway akan tampil di sini.">
            <x-slot:head>
                <tr class="bg-[#f8fafc] border-b border-[#e2e8f0] text-xs uppercase tracking-wider text-[#64748b]">
                    <th class="py-3 px-4 font-semibold">No. Transaksi</th>
                    <th class="py-3 px-4 font-semibold">Siswa / Rincian</th>
                    <th class="py-3 px-4 font-semibold">Waktu Pembayaran</th>
                    <th class="py-3 px-4 font-semibold text-right">Nominal</th>
                    <th class="py-3 px-4 font-semibold text-center w-36">Status</th>
                    <th class="py-3 px-4 font-semibold text-right w-24">Aksi</th>
                </tr>
            </x-slot:head>
            @foreach($payments as $payment)
                <tr wire:key="payment-{{ $payment->id }}" class="hover:bg-[#f8fafc]/80 transition-colors border-b border-[#f1f5f9] last:border-b-0">
                    <td class="py-3.5 px-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-[#e8f1fb] text-[#2872c8] flex items-center justify-center shrink-0">
                                <i data-lucide="receipt" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <a href="{{ route('payments.show', $payment) }}" class="font-semibold text-sm text-[#2872c8] hover:underline block leading-tight">
                                    {{ $payment->transaction_number }}
                                </a>
                                <span class="text-[11px] text-[#94a3b8] font-mono">{{ $payment->paymentCategory?->name ?? 'Pembayaran' }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="py-3.5 px-4">
                        <div class="font-medium text-sm text-[#111827]">{{ $payment->student?->name ?? '-' }}</div>
                        <div class="text-xs text-[#64748b] flex items-center gap-2 mt-0.5">
                            <span>NIS: <span class="font-mono text-[#334155]">{{ $payment->student?->nis ?: '-' }}</span></span>
                            @if($payment->student?->schoolClass)
                                <span>·</span>
                                <span class="badge badge-neutral text-[10px] px-1.5 py-0.5">{{ $payment->student->schoolClass->name }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="py-3.5 px-4">
                        <div class="text-xs font-medium text-[#334155]">{{ $payment->paid_at?->format('d/m/Y') ?? '-' }}</div>
                        <div class="text-[11px] text-[#94a3b8] mt-0.5">{{ $payment->paid_at?->format('H:i') }} WIB</div>
                    </td>
                    <td class="py-3.5 px-4 text-right">
                        <div class="font-semibold text-sm tabular text-[#111827]">{{ \App\Support\Formatter::rupiah((float) $payment->amount) }}</div>
                        <div class="text-[11px] text-[#64748b] mt-0.5">{{ $payment->details->pluck('paymentMethod.name')->filter()->implode(', ') ?: 'Kasir' }}</div>
                    </td>
                    <td class="py-3.5 px-4 text-center">
                        @php
                            $badgeVariant = match($payment->status->value) {
                                'approved' => 'success',
                                'rejected', 'cancelled' => 'danger',
                                default => 'warning',
                            };
                            $statusLabel = match($payment->status->value) {
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                                'cancelled' => 'Dibatalkan',
                                default => 'Menunggu',
                            };
                        @endphp
                        <x-ui.badge :variant="$badgeVariant">
                            {{ $statusLabel }}
                        </x-ui.badge>
                    </td>
                    <td class="py-3.5 px-4 text-right">
                        <a href="{{ route('payments.show', $payment) }}" class="btn btn-secondary btn-sm !px-2.5" title="Detail Pembayaran">
                            <span>Rincian</span>
                            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="p-3 border-t border-[#e2e8f0]">
            <x-ui.pagination :paginator="$payments" label="pembayaran" />
        </div>
    </div>
</div>

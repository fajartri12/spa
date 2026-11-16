@php use App\Support\Formatter; @endphp
@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pembayaran', 'url' => route('payments.index')], ['label' => 'Tagihan Digital']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Tagihan Digital</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Virtual Account dan QRIS dinamis per tagihan siswa. Pembayaran terverifikasi otomatis lewat notifikasi gateway.
            </p>
        </div>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">
            <i data-lucide="receipt" class="w-4 h-4"></i>
            <span>Daftar Pembayaran</span>
        </a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-ui.stat-card label="Menunggu Pembayaran" :value="number_format($pendingCount, 0, ',', '.') . ' tagihan'" icon="clock" tone="warning" />
        <x-ui.stat-card label="Total Nominal Dibayar" :value="Formatter::rupiah($paidTotal)" icon="badge-check" tone="success" />
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Referensi, kode bayar, nama, atau NIS..." icon="search" />
            </div>
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="$statusOptions" />
            <x-ui.select wire:model.live="providerFilter" name="providerFilter" label="Penyedia" placeholder="Semua Penyedia" :options="$providerOptions" />
        </div>
        <div class="mt-3">
            <button type="button" wire:click="resetFilters" class="btn btn-secondary btn-sm">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                <span>Reset Filter</span>
            </button>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$intents->isEmpty()" empty-title="Belum Ada Tagihan Digital" empty-description="Terbitkan VA/QRIS dari halaman detail tagihan siswa agar pembayaran dapat diverifikasi otomatis.">
            <x-slot:head>
                <tr>
                    <th>Referensi</th>
                    <th>Siswa</th>
                    <th>Tagihan</th>
                    <th>Penyedia / Kanal</th>
                    <th>Kode Bayar</th>
                    <th class="text-right">Nominal</th>
                    <th>Status</th>
                    <th>Kedaluwarsa</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($intents as $intent)
                <tr wire:key="intent-{{ $intent->id }}">
                    <td class="tabular font-medium text-[#111827]">{{ $intent->reference }}</td>
                    <td>
                        <div class="text-[#111827]">{{ $intent->student?->name ?? '-' }}</div>
                        <div class="text-[12px] text-[#8994a3]">{{ $intent->student?->nis }}</div>
                    </td>
                    <td class="tabular text-[#475569]">{{ $intent->studentBill?->bill_number ?? '-' }}</td>
                    <td>
                        <x-ui.badge :variant="$intent->provider->badgeVariant()">{{ $intent->provider->label() }}</x-ui.badge>
                        <div class="text-[12px] text-[#8994a3] mt-1">{{ $intent->channelLabel() }}</div>
                    </td>
                    <td class="tabular text-[#475569]">{{ $intent->pay_code ?? ($intent->qr_string ? 'QRIS' : '-') }}</td>
                    <td class="text-right tabular">{{ Formatter::rupiah((float) $intent->amount) }}</td>
                    <td>
                        <x-ui.badge :variant="$intent->status->badgeVariant()">{{ $intent->status->label() }}</x-ui.badge>
                        @if($intent->payment)
                            <div class="text-[12px] mt-1">
                                <a href="{{ route('payments.show', $intent->payment) }}" class="text-[#2872c8] hover:underline">
                                    {{ $intent->payment->transaction_number }}
                                </a>
                            </div>
                        @endif
                    </td>
                    <td class="text-[#475569]">
                        {{ $intent->expires_at?->format('d/m/Y H:i') ?? '-' }}
                        @if($intent->isExpired())
                            <div class="text-[12px] text-[#b9770e]">Lewat jatuh tempo</div>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            @if($intent->qr_string)
                                <span class="btn btn-ghost btn-sm" title="{{ $intent->qr_string }}">
                                    <i data-lucide="qr-code" class="w-4 h-4"></i>
                                    <span class="sr-only">Payload QRIS</span>
                                </span>
                            @endif
                            @can('payment-gateways.cancel')
                                @if($intent->status->isOpen())
                                    <button type="button" class="btn btn-danger btn-sm" wire:click="confirmCancel({{ $intent->id }})">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                        <span>Batalkan</span>
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$intents" label="tagihan digital" />
    </div>

    <x-ui.modal name="showCancelModal" title="Batalkan Tagihan Digital" subtitle="Nomor VA/QRIS tidak dapat dipakai lagi setelah dibatalkan.">
        <form wire:submit="cancel" class="space-y-4" novalidate>
            <x-ui.textarea wire:model="cancelReason" name="cancelReason" label="Alasan Pembatalan" placeholder="Contoh: Tagihan keliru / dibuat ulang dengan nominal baru" rows="3" required />
            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showCancelModal', false)">Batal</x-ui.button>
                <x-ui.button variant="danger" size="sm" type="submit" loading="cancel">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    <span>Batalkan Tagihan</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

@php use App\Support\Formatter; @endphp
@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pembayaran', 'url' => route('payments.index')], ['label' => 'Verifikasi Pembayaran']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Verifikasi Pembayaran</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Periksa lalu setujui atau tolak pembayaran siswa yang masih menunggu verifikasi.</p>
        </div>
        <a href="{{ route('payments.index') }}" class="btn btn-secondary">
            <i data-lucide="receipt" class="w-4 h-4"></i>
            <span>Daftar Pembayaran</span>
        </a>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <x-ui.card title="Menunggu Verifikasi">
            <p class="text-xl font-semibold tabular text-[#b9770e]">{{ number_format($payments->total(), 0, ',', '.') }} transaksi</p>
        </x-ui.card>
        <x-ui.card title="Total Nominal Menunggu">
            <p class="text-xl font-semibold tabular text-[#111827]">{{ Formatter::rupiah($totalAmount) }}</p>
        </x-ui.card>
    </div>

    <div class="card px-4 py-3">
        <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari Transaksi" placeholder="Nomor transaksi atau nama siswa..." icon="search" />
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$payments->isEmpty()" empty-title="Tidak Ada Pembayaran Menunggu" empty-description="Semua pembayaran sudah diverifikasi atau belum ada data baru.">
            <x-slot:head>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Tanggal</th>
                    <th>Siswa</th>
                    <th>Kategori</th>
                    <th class="text-right">Nominal</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </x-slot:head>
            @foreach($payments as $payment)
                <tr wire:key="verify-{{ $payment->id }}">
                    <td class="font-medium text-[#111827]">
                        <a href="{{ route('payments.show', $payment) }}" class="text-[#2872c8] hover:underline">{{ $payment->transaction_number }}</a>
                    </td>
                    <td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $payment->student?->name ?? '-' }}</td>
                    <td>{{ $payment->paymentCategory?->name ?? '-' }}</td>
                    <td class="text-right tabular">{{ Formatter::rupiah((float) $payment->amount) }}</td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('payments.show', $payment) }}" class="btn btn-ghost btn-sm" title="Lihat detail">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                                <span class="sr-only">Lihat</span>
                            </a>
                            @can('payments.verify')
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                    wire:click="approve({{ $payment->id }})"
                                    wire:confirm="Setujui pembayaran {{ $payment->transaction_number }} sebesar {{ Formatter::rupiah((float) $payment->amount) }}?"
                                >
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                    <span>Setujui</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-danger btn-sm"
                                    wire:click="startReject({{ $payment->id }})"
                                >
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                    <span>Tolak</span>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$payments" label="antrian verifikasi" />
    </div>

    <x-ui.modal name="rejectModal" title="Tolak Pembayaran" subtitle="Alasan penolakan akan dicatat pada riwayat verifikasi transaksi.">
        <form wire:submit="reject" class="space-y-4" novalidate>
            <x-ui.textarea wire:model="reason" name="reason" label="Alasan Penolakan" placeholder="Contoh: Bukti transfer tidak terbaca / nominal tidak sesuai" rows="3" required />
            <div class="flex items-center justify-end gap-2 border-t border-[#e1e6ed] pt-3">
                <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('rejectModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="danger" size="sm" type="submit" loading="reject">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    <span>Tolak Pembayaran</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Tabungan', 'url' => route('savings.index')], ['label' => 'Verifikasi Penarikan']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Verifikasi Penarikan Tabungan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Setujui penarikan agar saldo siswa berkurang dan kas keluar tercatat otomatis.
                @if($pendingCount > 0)
                    <span class="font-medium text-[#b45309]">{{ $pendingCount }} pengajuan menunggu.</span>
                @endif
            </p>
        </div>
        <a href="{{ route('savings.index') }}" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Kembali ke Tabungan</span>
        </a>
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input wire:model.live.debounce.300ms="search" name="search" label="Cari" placeholder="Nama siswa, NIS, atau nomor transaksi..." icon="search" />
            </div>
            <x-ui.select wire:model.live="statusFilter" name="statusFilter" label="Status" placeholder="Semua Status" :options="$statusOptions" />
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$withdrawals->isEmpty()" empty-title="Tidak Ada Pengajuan" empty-description="Pengajuan penarikan tabungan siswa akan muncul di sini untuk diverifikasi.">
            <x-slot:head>
                <tr>
                    <th class="w-44">Nomor</th>
                    <th>Siswa</th>
                    <th class="w-32">Diajukan</th>
                    <th class="w-36 text-right">Nominal</th>
                    <th class="w-40">Status</th>
                    <th class="w-40 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($withdrawals as $withdrawal)
                <tr wire:key="withdrawal-{{ $withdrawal->id }}">
                    <td class="tabular text-[#475569]">{{ $withdrawal->transaction_number }}</td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $withdrawal->account?->student?->name ?? '-' }}</div>
                        <div class="text-[12px] text-[#8994a3]">
                            {{ $withdrawal->account?->student?->nis }}
                            &middot; {{ $withdrawal->account?->student?->schoolClass?->name ?? '-' }}
                        </div>
                    </td>
                    <td class="text-[#475569]">
                        <div>{{ $withdrawal->transacted_at?->translatedFormat('d M Y') ?? '-' }}</div>
                        <div class="text-[12px] text-[#8994a3]">{{ $withdrawal->user?->name ?? '-' }}</div>
                    </td>
                    <td class="text-right tabular font-medium text-[#111827]">
                        {{ \App\Support\Formatter::rupiah((float) $withdrawal->amount) }}
                    </td>
                    <td>
                        @if($withdrawal->status === \App\Services\SavingsService::STATUS_PENDING)
                            <x-ui.badge variant="warning" dot>Menunggu Verifikasi</x-ui.badge>
                        @elseif($withdrawal->status === \App\Services\SavingsService::STATUS_POSTED)
                            <x-ui.badge variant="success" dot>Disetujui</x-ui.badge>
                            <div class="text-[11px] text-[#8994a3] mt-1">{{ $withdrawal->verifier?->name }}</div>
                        @else
                            <x-ui.badge variant="danger" dot>Ditolak</x-ui.badge>
                            @if($withdrawal->rejection_reason)
                                <div class="text-[11px] text-[#8994a3] mt-1">{{ $withdrawal->rejection_reason }}</div>
                            @endif
                        @endif
                    </td>
                    <td>
                        @if($withdrawal->status === \App\Services\SavingsService::STATUS_PENDING)
                            <div class="flex items-center justify-end gap-1">
                                <x-ui.button variant="primary" size="sm" type="button" wire:click="approve({{ $withdrawal->id }})" loading wire:target="approve({{ $withdrawal->id }})">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                    <span>Setujui</span>
                                </x-ui.button>
                                <x-ui.button variant="danger" size="sm" type="button" wire:click="confirmReject({{ $withdrawal->id }})">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                    <span>Tolak</span>
                                </x-ui.button>
                            </div>
                        @else
                            <div class="text-right text-[12px] text-[#8994a3]">
                                {{ $withdrawal->verified_at?->translatedFormat('d M Y H:i') ?? '-' }}
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$withdrawals" label="pengajuan" />
    </div>

    <x-ui.modal name="showRejectModal" title="Tolak Penarikan" size="sm" subtitle="Saldo siswa tidak berubah saat penarikan ditolak.">
        <form wire:submit="reject" class="space-y-4" novalidate>
            <x-ui.textarea wire:model="rejectionReason" name="rejectionReason" label="Alasan Penolakan" placeholder="Contoh: Saldo belum mencukupi setelah potongan administrasi" rows="3" required />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showRejectModal', false)">Batal</x-ui.button>
                <x-ui.button variant="danger" type="submit" loading="reject">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    <span>Tolak Penarikan</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

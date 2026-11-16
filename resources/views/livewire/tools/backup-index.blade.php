@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Peralatan'], ['label' => 'Backup Database']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Backup Database</h1>
            <p class="text-[13px] text-[#64748b] mt-1">Buat dan pulihkan cadangan basis data aplikasi.</p>
        </div>

        @can('backup.create')
            <x-ui.button variant="primary" wire:click="createBackup" wire:loading.attr="disabled" wire:target="createBackup">
                <i data-lucide="database-backup" class="w-4 h-4"></i>
                <span>Buat Backup</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$backups->isEmpty()" empty-title="Belum Ada Backup" empty-description="Buat backup pertama untuk mengamankan data aplikasi.">
            <x-slot:head><tr><th>Nama File</th><th class="w-28">Ukuran</th><th class="w-44">Dibuat</th><th class="w-40">Oleh</th><th class="w-28">Status</th><th class="w-40 text-right">Aksi</th></tr></x-slot:head>
            @foreach($backups as $backup)
                <tr wire:key="backup-{{ $backup->id }}">
                    <td class="font-mono text-xs">{{ $backup->filename }}</td>
                    <td class="tabular">{{ $backup->humanSize() }}</td>
                    <td class="whitespace-nowrap">{{ $backup->created_at?->format('d/m/Y H:i') }}</td>
                    <td>{{ $backup->creator?->name ?? 'Sistem' }}</td>
                    <td><x-ui.badge variant="success">{{ $backup->status }}</x-ui.badge></td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('backup.restore')
                                <button type="button" wire:click="confirmRestore({{ $backup->id }})" class="btn-icon" title="Restore" aria-label="Restore {{ $backup->filename }}">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                </button>
                            @endcan
                            @can('backup.delete')
                                <button type="button" wire:click="confirmDelete({{ $backup->id }})" class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]" title="Hapus" aria-label="Hapus {{ $backup->filename }}">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <x-ui.pagination :paginator="$backups" label="backup" />
    </div>

    @if($deletingId)
        <div class="card">
            <h2 class="font-semibold text-[#111827] text-sm">Konfirmasi Hapus Backup</h2>
            <div class="mt-3 flex items-center gap-2">
                <span class="text-[13px] text-[#64748b]">Hapus file backup ini secara permanen?</span>
                <x-ui.button variant="danger" size="sm" wire:click="delete">Hapus</x-ui.button>
                <x-ui.button variant="secondary" size="sm" wire:click="$set('deletingId', null)">Batal</x-ui.button>
            </div>
        </div>
    @endif

    <x-ui.modal name="showRestoreModal" title="Restore Database" subtitle="Tindakan ini akan menimpa seluruh data saat ini.">
        <div class="space-y-4">
            <p class="text-[13px] text-[#64748b]">Ketik <span class="font-mono font-semibold">RESTORE</span> untuk melanjutkan.</p>
            <x-ui.input wire:model="confirmText" name="confirmText" label="Konfirmasi" placeholder="RESTORE" />
            <div class="flex justify-end gap-2">
                <x-ui.button variant="secondary" wire:click="$set('showRestoreModal', false)">Batal</x-ui.button>
                <x-ui.button variant="danger" wire:click="restore" wire:loading.attr="disabled" wire:target="restore">Restore</x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>

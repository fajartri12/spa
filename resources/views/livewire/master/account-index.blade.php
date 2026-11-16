@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Akun Keuangan']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Akun Keuangan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Daftar akun buku besar yang menjadi dasar pencatatan arus kas dan laporan keuangan.
            </p>
        </div>

        @can('accounts.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Akun</span>
            </x-ui.button>
        @endcan
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Akun"
                    placeholder="Nama atau kode akun..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="typeFilter"
                name="typeFilter"
                label="Tipe Akun"
                placeholder="Semua Tipe"
                :options="$types"
            />

            <div class="flex items-end">
                <button type="button" wire:click="resetFilters" class="btn btn-secondary w-full">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Reset Filter</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$accounts->isEmpty()"
            empty-title="Belum Ada Akun Keuangan"
            empty-description="Tambahkan akun kas atau bank agar transaksi keuangan dapat dicatat.">
            <x-slot:head>
                <tr>
                    <th class="w-28">Kode</th>
                    <th>Nama Akun</th>
                    <th class="w-36">Tipe</th>
                    <th class="w-44">Saldo Awal</th>
                    <th class="w-32 text-center">Rekening</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($accounts as $account)
                <tr wire:key="account-{{ $account->id }}">
                    <td class="tabular font-medium text-[#111827]">{{ $account->code }}</td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $account->name }}</div>
                        @if($account->description)
                            <div class="text-[12px] text-[#8994a3] mt-0.5 line-clamp-1">{{ $account->description }}</div>
                        @endif
                    </td>
                    <td>
                        @if($account->type)
                            <x-ui.badge :variant="$account->type->badgeVariant()">{{ $account->type->label() }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="tabular text-[#475569]">{{ \App\Support\Formatter::rupiah((float) $account->opening_balance) }}</td>
                    <td class="text-center tabular text-[#475569]">{{ $account->bank_accounts_count }}</td>
                    <td>
                        @if($account->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('accounts.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $account->id }})"
                                    class="btn-icon"
                                    title="Ubah akun"
                                    aria-label="Ubah akun {{ $account->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('accounts.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $account->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus akun"
                                    aria-label="Hapus akun {{ $account->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$accounts" label="akun" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Akun Keuangan' : 'Tambah Akun Keuangan'"
        subtitle="Saldo awal digunakan sebagai titik mulai perhitungan arus kas."
        size="lg"
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input
                    wire:model="code"
                    name="code"
                    label="Kode Akun"
                    placeholder="Contoh: 1101"
                    required
                    icon="key-round"
                />

                <div class="sm:col-span-2">
                    <x-ui.input
                        wire:model="name"
                        name="name"
                        label="Nama Akun"
                        placeholder="Contoh: Kas Kecil Komite"
                        required
                    />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select
                    wire:model="type"
                    name="type"
                    label="Tipe Akun"
                    :options="$types"
                    required
                />

                <x-ui.input
                    wire:model="opening_balance"
                    name="opening_balance"
                    type="text"
                    inputmode="numeric"
                    label="Saldo Awal (Rp)"
                    required
                />
            </div>

            <x-ui.textarea
                wire:model="description"
                name="description"
                label="Keterangan"
                placeholder="Deskripsi singkat penggunaan akun"
                rows="2"
            />

            <label for="account_is_active" class="flex items-start gap-2.5 cursor-pointer select-none">
                <input
                    id="account_is_active"
                    type="checkbox"
                    wire:model="is_active"
                    class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                >
                <span>
                    <span class="block text-[13px] font-medium text-[#374151]">Akun aktif</span>
                    <span class="block text-[12px] text-[#8994a3]">Akun nonaktif tidak dapat dipilih pada transaksi baru.</span>
                </span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Akun' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal name="showDeleteModal" title="Hapus Akun" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Akun keuangan akan dihapus. Akun yang masih digunakan rekening bank tidak dapat dihapus. Lanjutkan?
            </p>
        </div>

        <div class="flex items-center justify-end gap-2 mt-5">
            <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showDeleteModal', false)">
                Batal
            </x-ui.button>
            <x-ui.button variant="danger" size="sm" type="button" wire:click="delete" loading="delete">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span>Ya, Hapus</span>
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>

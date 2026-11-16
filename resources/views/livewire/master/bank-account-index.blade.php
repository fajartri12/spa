@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Rekening Bank']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Rekening Bank</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Rekening tujuan pembayaran siswa, terhubung ke akun keuangan untuk pencatatan otomatis.
            </p>
        </div>

        @can('bank-accounts.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Rekening</span>
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
                    label="Cari Rekening"
                    placeholder="Nama bank, nomor, atau pemilik rekening..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="unitFilter"
                name="unitFilter"
                label="Unit"
                placeholder="Semua Unit"
                :options="$units"
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
        <x-ui.table :empty="$bankAccounts->isEmpty()"
            empty-title="Belum Ada Rekening Bank"
            empty-description="Tambahkan rekening bank agar siswa memiliki tujuan transfer pembayaran yang jelas.">
            <x-slot:head>
                <tr>
                    <th>Bank</th>
                    <th class="w-48">Nomor Rekening</th>
                    <th class="w-52">Atas Nama</th>
                    <th class="w-48">Akun Keuangan</th>
                    <th class="w-40">Unit</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($bankAccounts as $rekening)
                <tr wire:key="bank-account-{{ $rekening->id }}">
                    <td>
                        <div class="font-medium text-[#111827]">{{ $rekening->bank_name }}</div>
                        @if($rekening->branch)
                            <div class="text-[12px] text-[#8994a3] mt-0.5">{{ $rekening->branch }}</div>
                        @endif
                    </td>
                    <td class="tabular text-[#475569]">{{ $rekening->account_number }}</td>
                    <td class="text-[#475569]">{{ $rekening->account_name }}</td>
                    <td class="text-[#475569]">
                        @if($rekening->account)
                            {{ $rekening->account->code }} — {{ $rekening->account->name }}
                        @else
                            <span class="text-[#94a3b8]">Tidak terhubung</span>
                        @endif
                    </td>
                    <td class="text-[#475569]">{{ $rekening->unit?->name ?? 'Semua unit' }}</td>
                    <td>
                        @if($rekening->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('bank-accounts.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $rekening->id }})"
                                    class="btn-icon"
                                    title="Ubah rekening"
                                    aria-label="Ubah rekening {{ $rekening->bank_name }} {{ $rekening->account_number }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('bank-accounts.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $rekening->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus rekening"
                                    aria-label="Hapus rekening {{ $rekening->bank_name }} {{ $rekening->account_number }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$bankAccounts" label="rekening" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Rekening Bank' : 'Tambah Rekening Bank'"
        subtitle="Rekening ini akan ditampilkan pada portal orang tua sebagai tujuan transfer."
        size="lg"
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="bank_name"
                    name="bank_name"
                    label="Nama Bank"
                    placeholder="Contoh: Bank Jatim"
                    required
                    icon="landmark"
                />

                <x-ui.input
                    wire:model="branch"
                    name="branch"
                    label="Cabang"
                    placeholder="Contoh: Cabang Jombang"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="account_number"
                    name="account_number"
                    label="Nomor Rekening"
                    placeholder="Nomor rekening bank"
                    required
                />

                <x-ui.input
                    wire:model="account_name"
                    name="account_name"
                    label="Atas Nama"
                    placeholder="Nama pemilik rekening"
                    required
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select
                    wire:model="account_id"
                    name="account_id"
                    label="Akun Keuangan Terkait"
                    placeholder="Tidak terhubung"
                    :options="$accounts"
                    hint="Dipakai untuk pencatatan transaksi otomatis."
                />

                <x-ui.select
                    wire:model="unit_id"
                    name="unit_id"
                    label="Unit Pendidikan"
                    placeholder="Berlaku untuk semua unit"
                    :options="$units"
                />
            </div>

            <x-ui.textarea
                wire:model="description"
                name="description"
                label="Keterangan"
                placeholder="Catatan tambahan mengenai rekening ini"
                rows="2"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="sort_order"
                    name="sort_order"
                    type="number"
                    label="Urutan Tampil"
                    hint="Angka kecil tampil lebih dahulu."
                    required
                />

                <div class="flex items-end">
                    <label for="bank_account_is_active" class="flex items-start gap-2.5 cursor-pointer select-none pb-2">
                        <input
                            id="bank_account_is_active"
                            type="checkbox"
                            wire:model="is_active"
                            class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                        >
                        <span>
                            <span class="block text-[13px] font-medium text-[#374151]">Rekening aktif</span>
                            <span class="block text-[12px] text-[#8994a3]">Rekening nonaktif disembunyikan dari portal orang tua.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Rekening' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal name="showDeleteModal" title="Hapus Rekening" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Rekening akan dihapus dari daftar tujuan pembayaran. Riwayat transaksi yang sudah tercatat tidak terpengaruh. Lanjutkan?
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

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Kategori Pembayaran']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Kategori Pembayaran</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Jenis-jenis tagihan yang dikelola komite, beserta nominal standar dan jatuh temponya.
            </p>
        </div>

        @can('payment-categories.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Kategori</span>
            </x-ui.button>
        @endcan
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Kategori"
                    placeholder="Nama atau kode kategori..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="typeFilter"
                name="typeFilter"
                label="Jenis"
                placeholder="Semua Jenis"
                :options="$types"
            />

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
        <x-ui.table :empty="$categories->isEmpty()"
            empty-title="Belum Ada Kategori Pembayaran"
            empty-description="Buat kategori terlebih dahulu agar tagihan dapat digenerate.">
            <x-slot:head>
                <tr>
                    <th class="w-28">Kode</th>
                    <th>Nama Kategori</th>
                    <th class="w-32">Jenis</th>
                    <th class="w-40">Nominal Standar</th>
                    <th class="w-36">Jatuh Tempo</th>
                    <th class="w-40">Unit</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($categories as $category)
                <tr wire:key="category-{{ $category->id }}">
                    <td class="tabular font-medium text-[#111827]">{{ $category->code }}</td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $category->name }}</div>
                        @if($category->description)
                            <div class="text-[12px] text-[#8994a3] mt-0.5 line-clamp-1">{{ $category->description }}</div>
                        @endif
                    </td>
                    <td>
                        @if($category->type)
                            <x-ui.badge :variant="$category->type->badgeVariant()">{{ $category->type->label() }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="tabular text-[#475569]">{{ \App\Support\Formatter::rupiah((float) $category->default_amount) }}</td>
                    <td class="text-[#475569]">Tanggal {{ $category->due_day }}</td>
                    <td class="text-[#475569]">{{ $category->unit?->name ?? 'Semua unit' }}</td>
                    <td>
                        @if($category->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('payment-categories.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $category->id }})"
                                    class="btn-icon"
                                    title="Ubah kategori"
                                    aria-label="Ubah kategori {{ $category->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('payment-categories.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $category->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus kategori"
                                    aria-label="Hapus kategori {{ $category->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$categories" label="kategori" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Kategori Pembayaran' : 'Tambah Kategori Pembayaran'"
        subtitle="Nominal standar dipakai sebagai nilai awal saat tagihan digenerate."
        size="lg"
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input
                    wire:model="code"
                    name="code"
                    label="Kode"
                    placeholder="Contoh: SPP"
                    required
                    icon="tag"
                />

                <div class="sm:col-span-2">
                    <x-ui.input
                        wire:model="name"
                        name="name"
                        label="Nama Kategori"
                        placeholder="Contoh: Sumbangan Pembinaan Pendidikan"
                        required
                    />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.select
                    wire:model="type"
                    name="type"
                    label="Jenis Tagihan"
                    :options="$types"
                    required
                />

                <x-ui.input
                    wire:model="default_amount"
                    name="default_amount"
                    type="text"
                    inputmode="numeric"
                    label="Nominal Standar (Rp)"
                    required
                />

                <x-ui.input
                    wire:model="due_day"
                    name="due_day"
                    type="number"
                    label="Tanggal Jatuh Tempo"
                    hint="1 - 31"
                    required
                />
            </div>

            <x-ui.select
                wire:model="unit_id"
                name="unit_id"
                label="Unit Pendidikan"
                placeholder="Berlaku untuk semua unit"
                :options="$units"
            />

            <x-ui.textarea
                wire:model="description"
                name="description"
                label="Deskripsi"
                placeholder="Keterangan singkat kategori pembayaran"
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
                    <label for="category_is_active" class="flex items-start gap-2.5 cursor-pointer select-none pb-2">
                        <input
                            id="category_is_active"
                            type="checkbox"
                            wire:model="is_active"
                            class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                        >
                        <span>
                            <span class="block text-[13px] font-medium text-[#374151]">Kategori aktif</span>
                            <span class="block text-[12px] text-[#8994a3]">Hanya kategori aktif yang dapat dipilih saat generate tagihan.</span>
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
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Kategori' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal name="showDeleteModal" title="Hapus Kategori" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Kategori akan dihapus. Tagihan yang sudah terlanjur dibuat dari kategori ini tidak ikut dihapus. Lanjutkan?
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

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Unit Pendidikan']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Unit Pendidikan</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola daftar unit pendidikan yang terdaftar pada komite madrasah.
            </p>
        </div>

        @can('units.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Unit</span>
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
                    label="Cari Unit"
                    placeholder="Kode, nama, atau jenjang..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="statusFilter"
                name="statusFilter"
                label="Status"
                placeholder="Semua Status"
                :options="['1' => 'Aktif', '0' => 'Nonaktif']"
            />

            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="resetFilters"
                    class="btn btn-secondary w-full"
                >
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Reset Filter</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$units->isEmpty()"
            empty-title="Belum Ada Unit Pendidikan"
            empty-description="Tambahkan unit pendidikan pertama untuk mulai mengelola data master.">
            <x-slot:head>
                <tr>
                    <th class="w-16">Urutan</th>
                    <th class="w-28">Kode</th>
                    <th>Nama Unit</th>
                    <th class="w-44">Jenjang</th>
                    <th class="w-24 text-center">Kelas</th>
                    <th class="w-24 text-center">Siswa</th>
                    <th class="w-40">Tagihan Digital</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($units as $unit)
                <tr wire:key="unit-{{ $unit->id }}">
                    <td class="tabular text-[#8994a3]">{{ $unit->sort_order }}</td>
                    <td>
                        <span class="badge badge-neutral font-mono">{{ $unit->code }}</span>
                    </td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $unit->name }}</div>
                        @if($unit->description)
                            <div class="text-[12px] text-[#8994a3] mt-0.5">{{ $unit->description }}</div>
                        @endif
                    </td>
                    <td class="text-[#475569]">{{ $unit->level ?: '-' }}</td>
                    <td class="text-center tabular text-[#475569]">{{ $unit->school_classes_count }}</td>
                    <td class="text-center tabular text-[#475569]">{{ $unit->students_count }}</td>
                    <td>
                        @if($unit->payment_gateway_enabled && $unit->payment_provider)
                            <x-ui.badge :variant="$unit->payment_provider->badgeVariant()" dot>
                                {{ $unit->payment_provider->label() }}
                            </x-ui.badge>
                            @if($unit->payment_channel)
                                <div class="text-[12px] text-[#8994a3] mt-0.5 font-mono">{{ $unit->payment_channel }}</div>
                            @endif
                        @else
                            <span class="text-[12px] text-[#8994a3]">Manual</span>
                        @endif
                    </td>
                    <td>
                        @if($unit->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('units.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $unit->id }})"
                                    class="btn-icon"
                                    title="Ubah unit"
                                    aria-label="Ubah unit {{ $unit->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('units.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $unit->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus unit"
                                    aria-label="Hapus unit {{ $unit->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$units" label="unit" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Unit Pendidikan' : 'Tambah Unit Pendidikan'"
        subtitle="Lengkapi informasi unit pendidikan di bawah ini."
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="code"
                    name="code"
                    label="Kode Unit"
                    placeholder="Contoh: MAN7"
                    hint="Hanya huruf, angka, minus, dan garis bawah."
                    required
                    icon="tag"
                />

                <x-ui.input
                    wire:model="name"
                    name="name"
                    label="Nama Unit"
                    placeholder="Contoh: MAN 7 Jombang"
                    required
                    icon="building-2"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="level"
                    name="level"
                    label="Jenjang"
                    placeholder="Contoh: Madrasah Aliyah"
                    icon="layers"
                />

                <x-ui.input
                    wire:model="sort_order"
                    name="sort_order"
                    type="number"
                    label="Urutan Tampil"
                    hint="Angka kecil tampil lebih dahulu."
                    required
                />
            </div>

            <x-ui.textarea
                wire:model="description"
                name="description"
                label="Keterangan"
                placeholder="Deskripsi singkat unit pendidikan (opsional)."
                rows="3"
            />

            <div class="rounded-[10px] border border-[#e1e6ed] bg-[#f8fafc] p-4 space-y-3">
                <div>
                    <p class="text-[13px] font-semibold text-[#111827]">Tagihan Digital</p>
                    <p class="text-[12px] text-[#8994a3] mt-0.5">
                        Aktifkan agar unit ini dapat menerbitkan Virtual Account / QRIS dinamis per tagihan.
                    </p>
                </div>

                <label for="payment_gateway_enabled" class="flex items-start gap-2.5 cursor-pointer select-none">
                    <input
                        id="payment_gateway_enabled"
                        type="checkbox"
                        wire:model.live="payment_gateway_enabled"
                        class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                    >
                    <span>
                        <span class="block text-[13px] font-medium text-[#374151]">Aktifkan payment gateway</span>
                        <span class="block text-[12px] text-[#8994a3]">Bila nonaktif, pembayaran unit ini diverifikasi manual.</span>
                    </span>
                </label>

                @if($payment_gateway_enabled)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select
                            wire:model.live="payment_provider"
                            name="payment_provider"
                            label="Penyedia"
                            placeholder="Pilih penyedia..."
                            :options="$providerOptions"
                            required
                        />

                        <x-ui.select
                            wire:model="payment_channel"
                            name="payment_channel"
                            label="Kanal Bawaan"
                            placeholder="Mengikuti penyedia"
                            :options="$channelOptions"
                            :hint="$payment_provider !== '' ? 'Kanal yang ditawarkan saat menerbitkan tagihan digital.' : 'Pilih penyedia terlebih dahulu.'"
                        />
                    </div>

                    @if($payment_provider !== '' && ! ($providerAvailability[$payment_provider] ?? false))
                        <x-ui.alert type="warning" title="Kredensial belum lengkap">
                            Kredensial {{ $providerOptions[$payment_provider] ?? $payment_provider }} belum diisi pada
                            konfigurasi lingkungan. Penerbitan tagihan digital akan gagal sampai kredensial dilengkapi.
                        </x-ui.alert>
                    @endif
                @endif
            </div>

            <label for="is_active" class="flex items-start gap-2.5 cursor-pointer select-none">
                <input
                    id="is_active"
                    type="checkbox"
                    wire:model="is_active"
                    class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                >
                <span>
                    <span class="block text-[13px] font-medium text-[#374151]">Unit aktif</span>
                    <span class="block text-[12px] text-[#8994a3]">Unit nonaktif tidak muncul pada pilihan tagihan baru.</span>
                </span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Unit' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal
        name="showDeleteModal"
        title="Hapus Unit Pendidikan"
        size="sm"
    >
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Unit pendidikan akan dihapus dari daftar. Data kelas dan siswa yang terkait tidak akan dihapus,
                namun unit ini tidak lagi dapat dipilih. Lanjutkan?
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

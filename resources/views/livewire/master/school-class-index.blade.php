@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Kelas']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Kelas</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola daftar kelas per unit pendidikan dan tahun ajaran.
            </p>
        </div>

        @can('school-classes.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Kelas</span>
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
                    label="Cari Kelas"
                    placeholder="Nama kelas atau wali kelas..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="unitFilter"
                name="unitFilter"
                label="Unit Pendidikan"
                placeholder="Semua Unit"
                :options="$units"
            />

            <x-ui.select
                wire:model.live="yearFilter"
                name="yearFilter"
                label="Tahun Ajaran"
                placeholder="Semua Tahun"
                :options="$years"
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
        <x-ui.table :empty="$classes->isEmpty()"
            empty-title="Belum Ada Data Kelas"
            empty-description="Tambahkan kelas untuk unit pendidikan dan tahun ajaran yang sudah dibuat.">
            <x-slot:head>
                <tr>
                    <th>Nama Kelas</th>
                    <th class="w-48">Unit</th>
                    <th class="w-32">Tahun Ajaran</th>
                    <th class="w-40">Wali Kelas</th>
                    <th class="w-24 text-center">Siswa</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($classes as $class)
                <tr wire:key="class-{{ $class->id }}">
                    <td>
                        <div class="font-medium text-[#111827]">{{ $class->name }}</div>
                        @if($class->level)
                            <div class="text-[12px] text-[#8994a3] mt-0.5">{{ $class->level }}</div>
                        @endif
                    </td>
                    <td class="text-[#475569]">{{ $class->unit?->name ?? '-' }}</td>
                    <td class="text-[#475569]">{{ $class->academicYear?->name ?? '-' }}</td>
                    <td class="text-[#475569]">{{ $class->homeroom_teacher ?: '-' }}</td>
                    <td class="text-center">
                        <span class="tabular text-[#475569]">{{ $class->students_count }}</span>
                        @if($class->capacity > 0)
                            <span class="text-[11px] text-[#94a3b8]">/ {{ $class->capacity }}</span>
                        @endif
                    </td>
                    <td>
                        @if($class->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('school-classes.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $class->id }})"
                                    class="btn-icon"
                                    title="Ubah kelas"
                                    aria-label="Ubah kelas {{ $class->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('school-classes.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $class->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus kelas"
                                    aria-label="Hapus kelas {{ $class->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$classes" label="kelas" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Data Kelas' : 'Tambah Kelas'"
        subtitle="Kelas terikat pada satu unit pendidikan dan satu tahun ajaran."
        size="lg"
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select
                    wire:model="unit_id"
                    name="unit_id"
                    label="Unit Pendidikan"
                    :options="$units"
                    placeholder="Pilih unit"
                    required
                />

                <x-ui.select
                    wire:model="academic_year_id"
                    name="academic_year_id"
                    label="Tahun Ajaran"
                    :options="$years"
                    placeholder="Pilih tahun ajaran"
                    required
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input
                    wire:model="name"
                    name="name"
                    label="Nama Kelas"
                    placeholder="Contoh: X-IPA-1"
                    required
                    icon="school"
                />

                <x-ui.input
                    wire:model="level"
                    name="level"
                    label="Tingkat"
                    placeholder="Contoh: X"
                    icon="layers"
                />

                <x-ui.input
                    wire:model="capacity"
                    name="capacity"
                    type="number"
                    label="Kapasitas"
                    hint="0 berarti tidak dibatasi."
                    required
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="homeroom_teacher"
                    name="homeroom_teacher"
                    label="Wali Kelas"
                    placeholder="Nama wali kelas"
                    icon="user"
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

            <label for="class_is_active" class="flex items-start gap-2.5 cursor-pointer select-none">
                <input
                    id="class_is_active"
                    type="checkbox"
                    wire:model="is_active"
                    class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                >
                <span>
                    <span class="block text-[13px] font-medium text-[#374151]">Kelas aktif</span>
                    <span class="block text-[12px] text-[#8994a3]">Kelas nonaktif tidak muncul saat penempatan siswa baru.</span>
                </span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Kelas' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal name="showDeleteModal" title="Hapus Kelas" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Kelas akan dihapus dari daftar. Data siswa yang terkait tidak akan dihapus. Lanjutkan?
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

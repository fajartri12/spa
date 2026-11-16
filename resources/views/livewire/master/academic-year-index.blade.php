@php
    use App\Support\Formatter;
@endphp

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Tahun Ajaran']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Tahun Ajaran</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola periode tahun ajaran. Hanya satu tahun ajaran yang dapat aktif pada satu waktu.
            </p>
        </div>

        @can('academic-years.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Tahun Ajaran</span>
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
                    label="Cari Tahun Ajaran"
                    placeholder="Contoh: 2026/2027"
                    icon="search"
                />
            </div>

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
        <x-ui.table :empty="$years->isEmpty()"
            empty-title="Belum Ada Tahun Ajaran"
            empty-description="Tambahkan tahun ajaran pertama sebelum membuat data kelas dan tagihan.">
            <x-slot:head>
                <tr>
                    <th>Tahun Ajaran</th>
                    <th class="w-44">Mulai</th>
                    <th class="w-44">Selesai</th>
                    <th class="w-24 text-center">Kelas</th>
                    <th class="w-32">Status</th>
                    <th class="w-32 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($years as $year)
                <tr wire:key="academic-year-{{ $year->id }}">
                    <td>
                        <span class="font-medium text-[#111827]">{{ $year->name }}</span>
                    </td>
                    <td class="text-[#475569] tabular">{{ Formatter::date($year->start_date) }}</td>
                    <td class="text-[#475569] tabular">{{ Formatter::date($year->end_date) }}</td>
                    <td class="text-center tabular text-[#475569]">{{ $year->school_classes_count }}</td>
                    <td>
                        @if($year->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('academic-years.update')
                                @unless($year->is_active)
                                    <button
                                        type="button"
                                        wire:click="activate({{ $year->id }})"
                                        class="btn-icon hover:!bg-[#eaf7e3] hover:!text-[#438a21]"
                                        title="Jadikan aktif"
                                        aria-label="Aktifkan tahun ajaran {{ $year->name }}"
                                    >
                                        <i data-lucide="circle-check" class="w-4 h-4"></i>
                                    </button>
                                @endunless

                                <button
                                    type="button"
                                    wire:click="edit({{ $year->id }})"
                                    class="btn-icon"
                                    title="Ubah tahun ajaran"
                                    aria-label="Ubah tahun ajaran {{ $year->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('academic-years.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $year->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus tahun ajaran"
                                    aria-label="Hapus tahun ajaran {{ $year->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$years" label="tahun ajaran" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Tahun Ajaran' : 'Tambah Tahun Ajaran'"
        subtitle="Tentukan nama dan rentang tanggal tahun ajaran."
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <x-ui.input
                wire:model="name"
                name="name"
                label="Nama Tahun Ajaran"
                placeholder="Contoh: 2026/2027"
                required
                icon="calendar-range"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="start_date"
                    name="start_date"
                    type="date"
                    label="Tanggal Mulai"
                    required
                />

                <x-ui.input
                    wire:model="end_date"
                    name="end_date"
                    type="date"
                    label="Tanggal Selesai"
                    required
                />
            </div>

            <label for="is_active" class="flex items-start gap-2.5 cursor-pointer select-none">
                <input
                    id="is_active"
                    type="checkbox"
                    wire:model="is_active"
                    class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                >
                <span>
                    <span class="block text-[13px] font-medium text-[#374151]">Jadikan tahun ajaran aktif</span>
                    <span class="block text-[12px] text-[#8994a3]">Tahun ajaran lain akan otomatis dinonaktifkan.</span>
                </span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Tahun Ajaran' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal
        name="showDeleteModal"
        title="Hapus Tahun Ajaran"
        size="sm"
    >
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Tahun ajaran akan dihapus. Tindakan ini tidak dapat dibatalkan.
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

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB'], ['label' => 'Data Periode']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Data Periode PPDB</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Atur jadwal pembukaan dan penutupan penerimaan calon siswa.
            </p>
        </div>

        @can('admissions.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                <span>Periode Baru</span>
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
                    label="Cari Periode"
                    placeholder="Nama periode..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="statusFilter"
                name="statusFilter"
                label="Status"
                placeholder="Semua Status"
                :options="['draft' => 'Draft', 'open' => 'Dibuka', 'closed' => 'Ditutup']"
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
        <x-ui.table
            :empty="$periods->isEmpty()"
            empty-title="Belum Ada Periode PPDB"
            empty-description="Tambahkan periode penerimaan sebelum mendaftarkan calon siswa."
        >
            <x-slot:head>
                <tr>
                    <th>Nama Periode</th>
                    <th class="w-56">Rentang Pendaftaran</th>
                    <th class="w-28 text-center">Pendaftar</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($periods as $period)
                <tr wire:key="period-{{ $period->id }}">
                    <td>
                        <p class="font-medium text-[#111827]">{{ $period->name }}</p>
                        @if($period->description)
                            <p class="text-[12px] text-[#8994a3] mt-0.5 truncate max-w-[360px]">{{ $period->description }}</p>
                        @endif
                    </td>
                    <td class="tabular text-[#475569]">
                        {{ $period->starts_at->format('d/m/Y') }} &ndash; {{ $period->ends_at->format('d/m/Y') }}
                    </td>
                    <td class="text-center tabular text-[#475569]">
                        <a href="{{ route('admissions.applicants', ['periodFilter' => $period->id]) }}" class="text-[#2872c8] hover:underline" wire:navigate>
                            {{ $period->applicants_count }}
                        </a>
                    </td>
                    <td>
                        <x-ui.badge :variant="match($period->status) { 'open' => 'success', 'closed' => 'neutral', default => 'warning' }" dot>
                            {{ match($period->status) { 'open' => 'Dibuka', 'closed' => 'Ditutup', default => 'Draft' } }}
                        </x-ui.badge>
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('admissions.update')
                                <button type="button" wire:click="edit({{ $period->id }})" class="btn-icon" title="Ubah" aria-label="Ubah periode">
                                    <i data-lucide="pencil" class="w-4 h-4 text-[#2872c8]"></i>
                                </button>
                            @endcan
                            @can('admissions.delete')
                                <button type="button" wire:click="delete({{ $period->id }})" wire:confirm="Hapus periode ini?" class="btn-icon" title="Hapus" aria-label="Hapus periode">
                                    <i data-lucide="trash-2" class="w-4 h-4 text-[#c0392b]"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$periods" label="periode" />
    </div>

    {{-- Modal --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Periode PPDB' : 'Periode PPDB Baru'"
        subtitle="Lengkapi jadwal penerimaan calon siswa."
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <x-ui.input wire:model="name" name="name" label="Nama Periode" placeholder="PPDB 2027/2028" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="starts_at" name="starts_at" type="date" label="Tanggal Mulai" required />
                <x-ui.input wire:model="ends_at" name="ends_at" type="date" label="Tanggal Selesai" required />
            </div>

            <x-ui.select
                wire:model="status"
                name="status"
                label="Status"
                :options="['draft' => 'Draft', 'open' => 'Dibuka', 'closed' => 'Ditutup']"
                required
            />

            <x-ui.textarea wire:model="description" name="description" label="Deskripsi" rows="3" hint="Opsional." />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#e1e6ed]">
                <button type="button" @click="$dispatch('close-modal', 'showModal')" class="btn btn-secondary">Batal</button>
                <x-ui.button type="submit" variant="primary" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Periode</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

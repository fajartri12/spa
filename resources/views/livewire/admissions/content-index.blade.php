@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB'], ['label' => 'Konten Informasi'], ['label' => 'Pengumuman']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pengumuman PPDB</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola informasi dan pengumuman yang tampil pada portal pendaftaran.
            </p>
        </div>

        @can('admissions.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="megaphone" class="w-4 h-4"></i>
                <span>Pengumuman Baru</span>
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
                    label="Cari Pengumuman"
                    placeholder="Judul pengumuman..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="periodFilter"
                name="periodFilter"
                label="Periode"
                :options="$periodOptions"
                placeholder="Semua Periode"
            />

            <div class="flex items-end">
                <button type="button" wire:click="resetFilters" class="btn btn-secondary w-full">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Reset Filter</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Daftar Pengumuman --}}
    @if($contents->isEmpty())
        <div class="card !p-0">
            <x-ui.table :empty="true"
                empty-title="Belum Ada Pengumuman"
                empty-description="Tambahkan informasi atau pengumuman PPDB untuk calon siswa dan orang tua.">
            </x-ui.table>
        </div>
    @else
        <div class="grid gap-3 lg:grid-cols-2" wire:loading.class="opacity-60">
            @foreach($contents as $content)
                <div class="card p-4 flex flex-col gap-3" wire:key="content-{{ $content->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <x-ui.badge :variant="$content->is_published ? 'success' : 'neutral'" dot>
                                    {{ $content->is_published ? 'Terbit' : 'Draft' }}
                                </x-ui.badge>
                                <span class="text-[12px] text-[#8994a3]">{{ $content->admissionPeriod?->name ?? 'Umum' }}</span>
                            </div>
                            <h2 class="text-[14px] font-semibold text-[#111827] mt-1.5">{{ $content->title }}</h2>
                            <p class="text-[12px] text-[#8994a3] tabular">/{{ $content->slug }}</p>
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            @can('admissions.update')
                                <button type="button" wire:click="togglePublished({{ $content->id }})" class="btn-icon" title="{{ $content->is_published ? 'Sembunyikan' : 'Publikasikan' }}" aria-label="Ubah publikasi">
                                    <i data-lucide="{{ $content->is_published ? 'eye-off' : 'eye' }}" class="w-4 h-4 text-[#475569]"></i>
                                </button>
                                <button type="button" wire:click="edit({{ $content->id }})" class="btn-icon" title="Ubah" aria-label="Ubah pengumuman">
                                    <i data-lucide="pencil" class="w-4 h-4 text-[#2872c8]"></i>
                                </button>
                            @endcan
                            @can('admissions.delete')
                                <button type="button" wire:click="delete({{ $content->id }})" wire:confirm="Hapus pengumuman ini?" class="btn-icon" title="Hapus" aria-label="Hapus pengumuman">
                                    <i data-lucide="trash-2" class="w-4 h-4 text-[#c0392b]"></i>
                                </button>
                            @endcan
                        </div>
                    </div>

                    <p class="text-[13px] text-[#475569] leading-relaxed line-clamp-3">{{ Str::limit($content->body, 220) }}</p>

                    <p class="text-[12px] text-[#8994a3] mt-auto pt-2 border-t border-[#eef2f7]">
                        Diperbarui {{ $content->updated_at->diffForHumans() }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="card !p-0 overflow-hidden">
            <x-ui.pagination :paginator="$contents" label="pengumuman" />
        </div>
    @endif

    {{-- Modal --}}
    <x-ui.modal
        name="showModal"
        :title="$contentId ? 'Ubah Pengumuman PPDB' : 'Pengumuman PPDB Baru'"
        subtitle="Pengumuman yang terbit akan tampil pada portal pendaftaran."
        size="lg"
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <x-ui.input wire:model="contentTitle" name="contentTitle" label="Judul Pengumuman" placeholder="Pengumuman Jalur Prestasi" required />

            <x-ui.select
                wire:model="contentPeriodId"
                name="contentPeriodId"
                label="Periode PPDB"
                :options="$periodOptions"
                placeholder="Umum (semua periode)"
            />

            <x-ui.textarea wire:model="contentBody" name="contentBody" label="Isi Pengumuman" rows="8" hint="Informasi atau pengumuman yang tampil pada portal PPDB." required />

            <label class="flex items-center gap-2 text-sm text-[#475569]">
                <input type="checkbox" wire:model="contentPublished" class="rounded border-[#cbd5e1]">
                <span>Terbitkan pengumuman ini</span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#e1e6ed]">
                <button type="button" @click="$dispatch('close-modal', 'showModal')" class="btn btn-secondary">Batal</button>
                <x-ui.button type="submit" variant="primary" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Pengumuman</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'PPDB']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">PPDB</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola periode penerimaan dan pendaftaran calon siswa.
            </p>
        </div>

        @can('admissions.create')
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" wire:click="createPeriod">
                    <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                    <span>Periode Baru</span>
                </x-ui.button>
                <x-ui.button variant="primary" wire:click="createApplicant">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Daftar Calon Siswa</span>
                </x-ui.button>
                @if($activeTab === 'contents')
                    <x-ui.button variant="secondary" wire:click="createContent">
                        <i data-lucide="megaphone" class="w-4 h-4"></i>
                        <span>Konten Baru</span>
                    </x-ui.button>
                @endif
            </div>
        @endcan
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari"
                    placeholder="Nama periode, nama pendaftar, atau nomor"
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="periodFilter"
                name="periodFilter"
                label="Periode"
                :options="$periodOptions"
                placeholder="Semua periode"
            />

            <div class="flex items-end">
                <button type="button" wire:click="resetFilters" class="btn btn-secondary w-full">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Reset Filter</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Tab --}}
    <div class="flex gap-1 border-b border-[#e1e6ed]">
        <button
            type="button"
            wire:click="$set('activeTab', 'periods')"
            class="px-4 py-2 text-[13px] -mb-px border-b-2 {{ $activeTab === 'periods' ? 'border-[#2872c8] font-semibold text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#475569]' }}"
        >
            Periode Penerimaan
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'applicants')"
            class="px-4 py-2 text-[13px] -mb-px border-b-2 {{ $activeTab === 'applicants' ? 'border-[#2872c8] font-semibold text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#475569]' }}"
        >
            Calon Siswa
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'contents')"
            class="px-4 py-2 text-[13px] -mb-px border-b-2 {{ $activeTab === 'contents' ? 'border-[#2872c8] font-semibold text-[#2872c8]' : 'border-transparent text-[#64748b] hover:text-[#475569]' }}"
        >
            Informasi &amp; Pengumuman
        </button>
    </div>

    @if($activeTab === 'periods')
        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <x-ui.table
                :empty="$periods->isEmpty()"
                empty-title="Belum Ada Periode PPDB"
                empty-description="Tambahkan periode penerimaan sebelum mendaftarkan calon siswa."
            >
                <x-slot:head>
                    <tr>
                        <th>Nama Periode</th>
                        <th class="w-56">Rentang</th>
                        <th class="w-32">Status</th>
                        <th class="w-28 text-right">Pendaftar</th>
                    </tr>
                </x-slot:head>

                @foreach($periods as $period)
                    <tr wire:key="period-{{ $period->id }}">
                        <td class="font-medium text-[#111827]">{{ $period->name }}</td>
                        <td class="tabular text-[#475569]">
                            {{ $period->starts_at->format('d/m/Y') }} &ndash; {{ $period->ends_at->format('d/m/Y') }}
                        </td>
                        <td>
                            <x-ui.badge :variant="match($period->status) { 'open' => 'success', 'closed' => 'neutral', default => 'warning' }" dot>
                                {{ ucfirst($period->status) }}
                            </x-ui.badge>
                        </td>
                        <td class="text-right tabular text-[#475569]">{{ $period->applicants_count }}</td>
                    </tr>
                @endforeach
            </x-ui.table>

            <x-ui.pagination :paginator="$periods" label="periode" />
        </div>
    @elseif($activeTab === 'applicants')
        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <x-ui.table
                :empty="$applicants->isEmpty()"
                empty-title="Belum Ada Calon Siswa"
                empty-description="Daftarkan calon siswa pada periode PPDB yang tersedia."
            >
                <x-slot:head>
                    <tr>
                        <th class="w-48">Nomor Pendaftaran</th>
                        <th>Nama</th>
                        <th class="w-56">Periode</th>
                        <th class="w-32">Status</th>
                    </tr>
                </x-slot:head>

                @foreach($applicants as $applicant)
                    <tr wire:key="applicant-{{ $applicant->id }}">
                        <td class="font-medium tabular text-[#111827]">{{ $applicant->registration_number }}</td>
                        <td>
                            <a href="{{ route('admissions.show', $applicant) }}" class="font-medium text-[#4F46E5] hover:underline" wire:navigate>
                                {{ $applicant->name }}
                            </a>
                        </td>
                        <td class="text-[#475569]">{{ $applicant->admissionPeriod->name }}</td>
                        <td>
                            <x-ui.badge variant="info" dot>{{ ucfirst($applicant->status) }}</x-ui.badge>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <x-ui.pagination :paginator="$applicants" label="calon siswa" />
        </div>
    @else
        <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
            <x-ui.table
                :empty="$contents->isEmpty()"
                empty-title="Belum Ada Konten"
                empty-description="Tambahkan informasi atau pengumuman PPDB untuk calon siswa."
            >
                <x-slot:head>
                    <tr>
                        <th>Judul</th>
                        <th class="w-56">Periode</th>
                        <th class="w-32">Status</th>
                        <th class="w-32 text-right">Aksi</th>
                    </tr>
                </x-slot:head>

                @foreach($contents as $content)
                    <tr wire:key="content-{{ $content->id }}">
                        <td>
                            <p class="font-medium text-[#111827]">{{ $content->title }}</p>
                            <p class="text-[12px] text-[#8994a3]">/{{ $content->slug }}</p>
                        </td>
                        <td class="text-[#475569]">{{ $content->admissionPeriod?->name ?? 'Umum' }}</td>
                        <td>
                            <x-ui.badge :variant="$content->is_published ? 'success' : 'neutral'" dot>
                                {{ $content->is_published ? 'Terbit' : 'Draft' }}
                            </x-ui.badge>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                @can('admissions.update')
                                    <button type="button" wire:click="toggleContentPublished({{ $content->id }})" class="btn-icon" title="{{ $content->is_published ? 'Sembunyikan' : 'Publikasikan' }}" aria-label="Ubah publikasi">
                                        <i data-lucide="{{ $content->is_published ? 'eye-off' : 'eye' }}" class="w-4 h-4 text-[#475569]"></i>
                                    </button>
                                    <button type="button" wire:click="editContent({{ $content->id }})" class="btn-icon" title="Ubah" aria-label="Ubah konten">
                                        <i data-lucide="pencil" class="w-4 h-4 text-[#2872c8]"></i>
                                    </button>
                                @endcan
                                @can('admissions.delete')
                                    <button type="button" wire:click="deleteContent({{ $content->id }})" wire:confirm="Hapus konten ini?" class="btn-icon" title="Hapus" aria-label="Hapus konten">
                                        <i data-lucide="trash-2" class="w-4 h-4 text-[#c0392b]"></i>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <x-ui.pagination :paginator="$contents" label="konten" />
        </div>
    @endif

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="match($modalType) { 'period' => 'Tambah Periode PPDB', 'content' => $contentId ? 'Ubah Konten PPDB' : 'Konten PPDB Baru', default => 'Daftar Calon Siswa' }"
        subtitle="Lengkapi data yang diperlukan."
    >
        <form wire:submit="{{ $modalType === 'content' ? 'saveContent' : 'save' }}" class="space-y-4" novalidate>
            @if($modalType === 'period')
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

                <x-ui.textarea wire:model="description" name="description" label="Deskripsi" hint="Opsional." />
            @elseif($modalType === 'content')
                <x-ui.input wire:model="contentTitle" name="contentTitle" label="Judul" required />

                <x-ui.select
                    wire:model="contentPeriodId"
                    name="contentPeriodId"
                    label="Periode PPDB"
                    :options="$periodOptions"
                    placeholder="Umum (semua periode)"
                />

                <x-ui.textarea wire:model="contentBody" name="contentBody" label="Isi Konten" rows="6" hint="Informasi atau pengumuman yang tampil pada portal PPDB." required />

                <label class="flex items-center gap-2 text-sm text-[#475569]">
                    <input type="checkbox" wire:model="contentPublished" class="rounded border-[#cbd5e1]">
                    <span>Terbitkan konten ini</span>
                </label>
            @else
                <x-ui.select
                    wire:model="admission_period_id"
                    name="admission_period_id"
                    label="Periode PPDB"
                    :options="$periodOptions"
                    placeholder="Pilih periode"
                    required
                />

                <x-ui.input wire:model="applicantName" name="applicantName" label="Nama Lengkap" required />

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="email" name="email" type="email" label="Email" />
                    <x-ui.input wire:model="phone" name="phone" label="Nomor Telepon" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select
                        wire:model="gender"
                        name="gender"
                        label="Jenis Kelamin"
                        :options="['male' => 'Laki-laki', 'female' => 'Perempuan']"
                        placeholder="Pilih"
                    />
                    <x-ui.input wire:model="birth_place" name="birth_place" label="Tempat Lahir" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="birth_date" name="birth_date" type="date" label="Tanggal Lahir" />
                    <x-ui.input wire:model="parent_name" name="parent_name" label="Nama Orang Tua" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="parent_phone" name="parent_phone" label="Telepon Orang Tua" />
                    <x-ui.input wire:model="notes" name="notes" label="Catatan" />
                </div>

                <x-ui.textarea wire:model="address" name="address" label="Alamat" />
            @endif

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="{{ $modalType === 'content' ? 'saveContent' : 'save' }}">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

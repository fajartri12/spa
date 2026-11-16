@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Alumni']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Alumni</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Data lulusan per unit pendidikan beserta status lanjutan setelah lulus.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('alumni.export')
                <a href="{{ route('alumni.export', array_filter([
                    'unit' => $unitFilter,
                    'tahun-lulus' => $yearFilter,
                    'status' => $statusFilter,
                ])) }}" class="btn btn-secondary">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Export CSV</span>
                </a>
            @endcan

            @can('alumni.create')
                <x-ui.button variant="secondary" wire:click="openPull">
                    <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                    <span>Tarik dari Siswa Lulus</span>
                </x-ui.button>

                <x-ui.button variant="primary" wire:click="create">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Alumni</span>
                </x-ui.button>
            @endcan
        </div>
    </div>

    {{-- Ringkasan --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat-card label="Total Alumni" :value="number_format($totalAlumni, 0, ',', '.')" icon="graduation-cap" tone="primary" />

        @foreach(\App\Enums\AlumniStatus::cases() as $case)
            @php
                $tone = match ($case) {
                    \App\Enums\AlumniStatus::FurtherStudy => 'info',
                    \App\Enums\AlumniStatus::Working => 'success',
                    \App\Enums\AlumniStatus::Entrepreneur => 'purple',
                    default => 'warning',
                };
            @endphp

            <x-ui.stat-card
                :label="$case->label()"
                :value="number_format($statusCounts[$case->value] ?? 0, 0, ',', '.')"
                icon="users"
                :tone="$tone"
            />
        @endforeach
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Alumni"
                    placeholder="Nama, NIS, kelas, jurusan, atau pekerjaan..."
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
                label="Tahun Lulus"
                placeholder="Semua Tahun"
                :options="$years"
            />

            <div class="grid gap-3 sm:grid-cols-2 lg:col-span-2">
                <x-ui.select
                    wire:model.live="statusFilter"
                    name="statusFilter"
                    label="Status"
                    placeholder="Semua Status"
                    :options="$statuses"
                />

                <div class="flex items-end">
                    <button type="button" wire:click="resetFilters" class="btn btn-secondary w-full">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        <span>Reset Filter</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$alumni->isEmpty()"
            empty-title="Belum Ada Data Alumni"
            empty-description="Tambahkan alumni secara manual atau tarik dari daftar siswa yang sudah berstatus lulus.">
            <x-slot:head>
                <tr>
                    <th class="w-32">NIS / NISN</th>
                    <th>Nama Alumni</th>
                    <th class="w-44">Unit</th>
                    <th class="w-36">Kelas / Jurusan</th>
                    <th class="w-24">Tahun Lulus</th>
                    <th class="w-40">Status Setelah Lulus</th>
                    <th class="w-32">Kontak</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($alumni as $row)
                <tr wire:key="alumni-{{ $row->id }}">
                    <td class="tabular">
                        <div class="font-medium text-[#111827]">{{ $row->nis ?: '-' }}</div>
                        @if($row->nisn)
                            <div class="text-[11px] text-[#8994a3] mt-0.5">{{ $row->nisn }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="font-medium text-[#111827]">{{ $row->name }}</div>
                        <div class="text-[11px] text-[#8994a3] mt-0.5">
                            {{ $row->gender?->label() ?? '-' }}
                            @if($row->birth_place || $row->birth_date)
                                &middot; {{ $row->birth_place ?: '-' }}, {{ $row->birth_date?->format('d/m/Y') ?? '-' }}
                            @endif
                        </div>
                    </td>
                    <td class="text-[#475569]">{{ $row->unit?->name ?? '-' }}</td>
                    <td class="text-[#475569]">
                        <div>{{ $row->class_name ?: '-' }}</div>
                        @if($row->major)
                            <div class="text-[11px] text-[#8994a3] mt-0.5">{{ $row->major }}</div>
                        @endif
                    </td>
                    <td class="tabular text-[#475569]">{{ $row->graduation_year }}</td>
                    <td>
                        @if($row->status)
                            <x-ui.badge :variant="$row->status->badgeVariant()" dot>{{ $row->status->label() }}</x-ui.badge>
                        @endif

                        @if($row->status === \App\Enums\AlumniStatus::FurtherStudy && $row->higher_education)
                            <div class="text-[11px] text-[#8994a3] mt-1">{{ $row->higher_education }}</div>
                        @elseif($row->occupation || $row->workplace)
                            <div class="text-[11px] text-[#8994a3] mt-1">
                                {{ $row->occupation ?: '-' }}{{ $row->workplace ? ' · '.$row->workplace : '' }}
                            </div>
                        @endif
                    </td>
                    <td class="text-[#475569]">
                        <div>{{ $row->phone ?: '-' }}</div>
                        @if($row->email)
                            <div class="text-[11px] text-[#8994a3] mt-0.5 break-all">{{ $row->email }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('alumni.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $row->id }})"
                                    class="btn-icon"
                                    title="Ubah alumni"
                                    aria-label="Ubah alumni {{ $row->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('alumni.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $row->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus alumni"
                                    aria-label="Hapus alumni {{ $row->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$alumni" label="alumni" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Data Alumni' : 'Tambah Alumni'"
        subtitle="Isi identitas alumni beserta status setelah lulus."
        size="lg"
    >
        <form wire:submit="save" class="space-y-5" novalidate>
            {{-- Identitas --}}
            <div>
                <h3 class="text-[12px] font-semibold uppercase tracking-wider text-[#8994a3] mb-3">Identitas Alumni</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="name" name="name" label="Nama Lengkap" placeholder="Nama alumni" required icon="user" />

                    <x-ui.select wire:model="gender" name="gender" label="Jenis Kelamin" :options="$genders" required />
                </div>

                <div class="grid gap-4 sm:grid-cols-2 mt-4">
                    <x-ui.input wire:model="nis" name="nis" label="NIS" placeholder="Nomor induk siswa" hint="Bila cocok dengan siswa pada unit yang sama, data akan tertaut otomatis." />
                    <x-ui.input wire:model="nisn" name="nisn" label="NISN" placeholder="Nomor induk siswa nasional" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3 mt-4">
                    <x-ui.input wire:model="birth_place" name="birth_place" label="Tempat Lahir" placeholder="Kota kelahiran" />
                    <x-ui.input wire:model="birth_date" name="birth_date" type="date" label="Tanggal Lahir" />
                    <x-ui.input wire:model="phone" name="phone" label="No. HP" placeholder="08xxxxxxxxxx" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2 mt-4">
                    <x-ui.input wire:model="email" name="email" type="email" label="Email" placeholder="nama@contoh.com" />
                    <x-ui.input wire:model="address" name="address" label="Alamat" placeholder="Alamat tempat tinggal" />
                </div>
            </div>

            <div class="divider"></div>

            {{-- Akademik --}}
            <div>
                <h3 class="text-[12px] font-semibold uppercase tracking-wider text-[#8994a3] mb-3">Data Kelulusan</h3>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select wire:model="unit_id" name="unit_id" label="Unit Pendidikan" placeholder="Pilih unit" :options="$units" required />
                    <x-ui.input wire:model="graduation_year" name="graduation_year" type="number" label="Tahun Lulus" placeholder="Contoh: 2026" required />
                    <x-ui.input wire:model="class_name" name="class_name" label="Kelas Terakhir" placeholder="Contoh: XII-IPA-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2 mt-4">
                    <x-ui.input wire:model="major" name="major" label="Jurusan" placeholder="Contoh: IPA / IPS / Keagamaan" />
                    <x-ui.select wire:model="status" name="status" label="Status Setelah Lulus" :options="$statuses" required />
                </div>
            </div>

            <div class="divider"></div>

            {{-- Status lanjutan --}}
            <div>
                <h3 class="text-[12px] font-semibold uppercase tracking-wider text-[#8994a3] mb-3">Aktivitas Setelah Lulus</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input wire:model="higher_education" name="higher_education" label="Perguruan Tinggi / Sekolah Lanjutan" placeholder="Contoh: UIN Malang" hint="Untuk status melanjutkan studi." />
                    <x-ui.input wire:model="workplace" name="workplace" label="Tempat Bekerja / Usaha" placeholder="Contoh: PT Sumber Makmur" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2 mt-4">
                    <x-ui.input wire:model="occupation" name="occupation" label="Pekerjaan" placeholder="Contoh: Karyawan / Wirausaha" />
                </div>

                <div class="mt-4">
                    <x-ui.textarea wire:model="notes" name="notes" label="Catatan" rows="3" placeholder="Catatan tambahan mengenai alumni." />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Alumni' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Tarik dari Siswa Lulus --}}
    <x-ui.modal
        name="showPullModal"
        title="Tarik dari Siswa Lulus"
        subtitle="Pilih siswa berstatus lulus yang belum tercatat sebagai alumni."
        size="lg"
    >
        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model.live="pull_unit_id" name="pull_unit_id" label="Unit Pendidikan" placeholder="Semua Unit" :options="$units" />
                <x-ui.input wire:model="pull_graduation_year" name="pull_graduation_year" type="number" label="Tahun Lulus" hint="Dipakai untuk semua siswa yang dipilih." required />
            </div>

            @if($candidates->isEmpty())
                <x-ui.alert type="info" title="Tidak ada siswa lulus yang bisa ditarik">
                    Semua siswa berstatus lulus pada filter ini sudah tercatat sebagai alumni.
                </x-ui.alert>
            @else
                <div class="max-h-[320px] overflow-y-auto scrollbar-thin border border-[#e1e6ed] rounded-[6px]">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-10"></th>
                                <th class="w-32">NIS</th>
                                <th>Nama</th>
                                <th class="w-40">Unit</th>
                                <th class="w-32">Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($candidates as $candidate)
                                <tr wire:key="candidate-{{ $candidate->id }}">
                                    <td>
                                        <input
                                            type="checkbox"
                                            wire:model="selectedStudents"
                                            value="{{ $candidate->id }}"
                                            id="candidate-{{ $candidate->id }}"
                                            class="w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                                        >
                                    </td>
                                    <td class="tabular">
                                        <label for="candidate-{{ $candidate->id }}" class="cursor-pointer">{{ $candidate->nis }}</label>
                                    </td>
                                    <td>
                                        <label for="candidate-{{ $candidate->id }}" class="cursor-pointer font-medium text-[#111827]">{{ $candidate->name }}</label>
                                    </td>
                                    <td class="text-[#475569]">{{ $candidate->unit?->name ?? '-' }}</td>
                                    <td class="text-[#475569]">{{ $candidate->schoolClass?->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-[12px] text-[#64748b]">
                    {{ $candidates->count() }} siswa lulus siap ditarik.
                    @error('selectedStudents')<span class="block text-[#c0392b] mt-1">{{ $message }}</span>@enderror
                </p>
            @endif
        </div>

        <div class="flex items-center justify-end gap-2 mt-5 pt-3 border-t border-[#eef2f7]">
            <x-ui.button variant="secondary" type="button" wire:click="$set('showPullModal', false)">Batal</x-ui.button>
            @if(! $candidates->isEmpty())
                <x-ui.button variant="primary" type="button" wire:click="pullFromGraduates" loading="pullFromGraduates">
                    <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                    <span>Tarik Jadi Alumni</span>
                </x-ui.button>
            @endif
        </div>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal name="showDeleteModal" title="Hapus Alumni" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Data alumni akan dihapus dari daftar. Data siswa yang tertaut tidak ikut terhapus. Lanjutkan?
            </p>
        </div>

        <div class="flex items-center justify-end gap-2 mt-5">
            <x-ui.button variant="secondary" size="sm" type="button" wire:click="$set('showDeleteModal', false)">Batal</x-ui.button>
            <x-ui.button variant="danger" size="sm" type="button" wire:click="delete" loading="delete">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span>Ya, Hapus</span>
            </x-ui.button>
        </div>
    </x-ui.modal>
</div>

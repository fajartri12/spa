@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Siswa']]" />
@endsection

<div class="space-y-4">
    @if($importErrors = session('import_errors'))
        <x-ui.alert type="warning" title="Sebagian baris gagal diimpor">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach($importErrors as $importError)
                    <li>{{ $importError }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Siswa</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola data induk siswa beserta penempatan kelas dan data orang tua.
                @if($activeYear)
                    <span class="text-[#8994a3]">Tahun ajaran aktif: {{ $activeYear }}.</span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2" x-data="{ importOpen: false }">
            @can('students.import')
                <x-ui.button type="button" variant="secondary" size="sm" icon="upload" x-on:click="importOpen = true">Import CSV</x-ui.button>
            @endcan

            @can('students.export')
                <a href="{{ route('students.export') }}" class="btn btn-secondary">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Export CSV</span>
                </a>
            @endcan

            @can('students.create')
                <x-ui.button variant="primary" wire:click="create">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span>Tambah Siswa</span>
                </x-ui.button>
            @endcan

            @can('students.import')
                <div x-show="importOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6" role="dialog" aria-modal="true" aria-label="Import Siswa">
                    <div class="absolute inset-0 bg-[#0f172a]/50" x-on:click="importOpen = false"></div>
                    <div class="relative w-full max-w-[480px] bg-white rounded-[8px] shadow-[0_12px_32px_-8px_rgb(16_24_40/0.24)]">
                        <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-[#e1e6ed]">
                            <div>
                                <h2 class="text-[16px] font-semibold text-gray-900">Import Siswa</h2>
                                <p class="text-[12px] text-[#8994a3] mt-0.5">Unggah file CSV sesuai urutan kolom template.</p>
                            </div>
                            <button type="button" x-on:click="importOpen = false" class="btn-icon -mr-1" aria-label="Tutup dialog">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>

                        <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data" class="px-5 py-4 space-y-3">
                            @csrf
                            <input type="file" name="file" accept=".csv,text/csv" required
                                   class="block w-full text-[12px] file:mr-2 file:rounded-[6px] file:border-0 file:bg-[#eaf2fc] file:px-2 file:py-1.5 file:text-[#2872c8] file:font-medium">
                            <p class="text-[12px] text-[#64748b]">
                                Kolom: unit_code, nis, nisn, name, gender, birth_place, birth_date, religion, address, phone, email, parent_name, parent_phone, parent_relation, entry_year, status.
                                NIS duplikat dilewati.
                            </p>
                            <div class="flex items-center justify-end gap-2 pt-1">
                                <x-ui.button type="button" variant="ghost" size="sm" x-on:click="importOpen = false">Batal</x-ui.button>
                                <x-ui.button type="submit" variant="primary" size="sm" icon="upload">Import</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Siswa"
                    placeholder="Nama, NIS, NISN, atau orang tua..."
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

            <x-ui.select
                wire:model.live="classFilter"
                name="classFilter"
                label="Kelas"
                placeholder="Semua Kelas"
                :options="$filterClasses"
            />

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
                    <span>Reset</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Tabel --}}
    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$students->isEmpty()"
            empty-title="Belum Ada Data Siswa"
            empty-description="Tambahkan siswa terlebih dahulu sebelum membuat tagihan pembayaran.">
            <x-slot:head>
                <tr>
                    <th class="w-32 p-2 text-xs">NIS / NISN</th>
                    <th class="p-2 text-xs">Nama Siswa</th>
                    <th class="w-44 p-2 text-xs">Unit</th>
                    <th class="w-32 p-2 text-xs">Kelas</th>
                    <th class="w-32 p-2 text-xs">Jurusan</th>
                    <th class="w-44 p-2 text-xs">Orang Tua</th>
                    <th class="w-28 p-2 text-xs">Status</th>
                    <th class="w-24 p-2 text-xs text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($students as $student)
                <tr wire:key="student-{{ $student->id }}">
                    <td class="p-2 text-xs">
                        <div class="tabular font-medium text-[#111827]">{{ $student->nis }}</div>
                        @if($student->nisn)
                            <div class="tabular text-[11px] text-[#8994a3] mt-0.5">{{ $student->nisn }}</div>
                        @endif
                    </td>
                    <td class="p-2 text-xs">
                        <div class="font-medium text-[#111827]">{{ $student->name }}</div>
                        <div class="text-[11px] text-[#8994a3] mt-0.5">
                            {{ $student->gender?->label() ?? '-' }}
                            @if($student->birth_place || $student->birth_date)
                                &middot; {{ $student->birth_place ?: '-' }}, {{ $student->birth_date?->format('d/m/Y') ?? '-' }}
                            @endif
                        </div>
                    </td>
                    <td class="p-2 text-xs text-[#475569]">{{ $student->unit?->name ?? '-' }}</td>
                    <td class="p-2 text-xs text-[#475569]">{{ $student->schoolClass?->name ?? '-' }}</td>
                    <td class="p-2 text-xs text-[#475569]">{{ $student->major ?? '-' }}</td>
                    <td class="p-2 text-xs">
                        <div class="text-[12px] text-[#475569]">{{ $student->parent_name ?: '-' }}</div>
                        @if($student->parent_phone)
                            <div class="tabular text-[11px] text-[#8994a3]">{{ $student->parent_phone }}</div>
                        @endif
                    </td>
                    <td class="p-2 text-xs">
                        @if($student->status)
                            <x-ui.badge :variant="$student->status->badgeVariant()" dot>
                                {{ $student->status->label() }}
                            </x-ui.badge>
                        @endif
                    </td>
                    <td class="p-2 text-xs">
                        <div class="flex items-center justify-end gap-1">
                            @can('students.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $student->id }})"
                                    class="btn-icon"
                                    title="Ubah siswa"
                                    aria-label="Ubah siswa {{ $student->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('students.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $student->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus siswa"
                                    aria-label="Hapus siswa {{ $student->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('students.update')
                                <button
                                    type="button"
                                    wire:click="resetPortalPassword({{ $student->id }})"
                                    class="btn-icon"
                                    title="Reset password portal siswa"
                                    aria-label="Reset password portal siswa {{ $student->name }}"
                                >
                                    <i data-lucide="key" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$students" label="siswa" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Data Siswa' : 'Tambah Siswa'"
        subtitle="Lengkapi identitas siswa, penempatan kelas, dan kontak orang tua."
        size="lg"
    >
        <form wire:submit="save" class="space-y-5" novalidate>
            {{-- Identitas --}}
            <div>
                <h3 class="text-[12px] font-semibold uppercase tracking-wider text-[#8994a3] mb-3">Identitas Siswa</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input
                        wire:model="name"
                        name="name"
                        label="Nama Lengkap"
                        placeholder="Nama siswa"
                        required
                        icon="user"
                    />

                    <x-ui.select
                        wire:model="gender"
                        name="gender"
                        label="Jenis Kelamin"
                        :options="$genders"
                        required
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2 mt-4">
                    <x-ui.input
                        wire:model="nis"
                        name="nis"
                        label="NIS"
                        placeholder="Nomor induk siswa"
                        required
                    />

                    <x-ui.input
                        wire:model="nisn"
                        name="nisn"
                        label="NISN"
                        placeholder="Nomor induk siswa nasional"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-3 mt-4">
                    <x-ui.input
                        wire:model="birth_place"
                        name="birth_place"
                        label="Tempat Lahir"
                        placeholder="Kota kelahiran"
                    />

                    <x-ui.input
                        wire:model="birth_date"
                        name="birth_date"
                        type="date"
                        label="Tanggal Lahir"
                    />

                    <x-ui.input
                        wire:model="religion"
                        name="religion"
                        label="Agama"
                        placeholder="Contoh: Islam"
                    />
                </div>
            </div>

            <div class="divider"></div>

            {{-- Penempatan --}}
            <div>
                <h3 class="text-[12px] font-semibold uppercase tracking-wider text-[#8994a3] mb-3">Penempatan Akademik</h3>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-ui.select
                        wire:model.live="unit_id"
                        name="unit_id"
                        label="Unit Pendidikan"
                        placeholder="Pilih unit"
                        :options="$units"
                        required
                    />

                    <x-ui.select
                        wire:model="school_class_id"
                        name="school_class_id"
                        label="Kelas"
                        placeholder="Belum ditempatkan"
                        :options="$formClasses"
                        hint="Hanya kelas pada unit yang sama."
                    />

                    <x-ui.input
                        wire:model="major"
                        name="major"
                        label="Jurusan"
                        placeholder="Contoh: IPA / IPS / Keagamaan"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-2 mt-4">
                    <x-ui.input
                        wire:model="entry_year"
                        name="entry_year"
                        type="number"
                        label="Tahun Masuk"
                        required
                    />

                    <x-ui.select
                        wire:model="status"
                        name="status"
                        label="Status Siswa"
                        :options="$statuses"
                        required
                    />
                </div>
            </div>

            <div class="divider"></div>

            {{-- Kontak & Orang Tua --}}
            <div>
                <h3 class="text-[12px] font-semibold uppercase tracking-wider text-[#8994a3] mb-3">Kontak &amp; Orang Tua</h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input
                        wire:model="phone"
                        name="phone"
                        label="Telepon Siswa"
                        placeholder="08xxxxxxxxxx"
                    />

                    <x-ui.input
                        wire:model="email"
                        name="email"
                        type="email"
                        label="Email Siswa"
                        placeholder="nama@example.com"
                        icon="mail"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-3 mt-4">
                    <x-ui.input
                        wire:model="parent_name"
                        name="parent_name"
                        label="Nama Orang Tua / Wali"
                        placeholder="Nama orang tua"
                    />

                    <x-ui.input
                        wire:model="parent_phone"
                        name="parent_phone"
                        label="Telepon Orang Tua"
                        placeholder="08xxxxxxxxxx"
                    />

                    <x-ui.input
                        wire:model="parent_relation"
                        name="parent_relation"
                        label="Hubungan"
                        placeholder="Contoh: Ayah"
                    />
                </div>

                <div class="mt-4">
                    <x-ui.textarea
                        wire:model="address"
                        name="address"
                        label="Alamat"
                        placeholder="Alamat tempat tinggal siswa"
                        rows="2"
                    />
                </div>

                <div class="mt-4">
                    <x-ui.textarea
                        wire:model="notes"
                        name="notes"
                        label="Catatan"
                        placeholder="Catatan tambahan (opsional)"
                        rows="2"
                    />
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Siswa' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal name="showDeleteModal" title="Hapus Siswa" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Data siswa akan dihapus dari daftar aktif. Riwayat tagihan dan pembayaran yang sudah ada tidak akan dihapus. Lanjutkan?
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

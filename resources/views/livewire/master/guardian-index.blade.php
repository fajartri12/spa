@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Orang Tua / Wali']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Orang Tua / Wali</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola akun portal orang tua, hubungkan dengan siswa, dan terbitkan password.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can('guardians.export')
                <a href="{{ route('guardians.export', array_filter([
                    'unit' => $unitFilter,
                    'status' => $statusFilter,
                ])) }}" class="btn btn-secondary">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Export CSV</span>
                </a>
            @endcan

            @can('guardians.create')
                <x-ui.button
                    variant="secondary"
                    wire:click="syncFromStudents"
                    wire:confirm="Sinkronkan data orang tua dari data siswa? Akun baru akan dibuat dari kolom orang tua pada data siswa."
                    loading="syncFromStudents"
                >
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Sinkronkan dari Data Siswa</span>
                </x-ui.button>

                <x-ui.button variant="primary" wire:click="create">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Orang Tua</span>
                </x-ui.button>
            @endcan
        </div>
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Orang Tua"
                    placeholder="Nama, nomor HP, atau nama anak..."
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
                wire:model.live="statusFilter"
                name="statusFilter"
                label="Status Akun"
                placeholder="Semua Status"
                :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']"
            />
        </div>

        <div class="flex justify-end mt-3">
            <button type="button" wire:click="resetFilters" class="btn btn-secondary">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                <span>Reset Filter</span>
            </button>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table
            :empty="$guardians->isEmpty()"
            empty-title="Belum Ada Data Orang Tua"
            empty-description="Tambahkan akun orang tua lalu hubungkan dengan siswa agar dapat masuk ke portal."
        >
            <x-slot:head>
                <tr>
                    <th>Nama / Hubungan</th>
                    <th class="w-44">Nomor HP</th>
                    <th>Siswa Terhubung</th>
                    <th class="w-32">Password</th>
                    <th class="w-28">Status</th>
                    <th class="w-32 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($guardians as $guardian)
                <tr wire:key="guardian-{{ $guardian->id }}">
                    <td>
                        <div class="font-medium text-[#111827]">{{ $guardian->name }}</div>
                        <div class="text-[12px] text-[#8994a3] mt-0.5">{{ $guardian->relation?->label() ?? 'Wali' }}</div>
                    </td>
                    <td class="tabular text-[#475569]">{{ $guardian->phone }}</td>
                    <td>
                        @if($guardian->students->isEmpty())
                            <span class="text-[12px] text-[#c0392b]">Belum terhubung</span>
                        @else
                            <div class="flex flex-wrap gap-1">
                                @foreach($guardian->students as $child)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[#eef4fb] px-2 py-0.5 text-[11px] font-medium text-[#1d5b9e]">
                                        {{ $child->name }}
                                        @if($child->unit)
                                            <span class="text-[#5b8cc4]">&middot; {{ $child->unit->name }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($guardian->hasPassword())
                            <x-ui.badge variant="success" dot>Terbit</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" dot>Belum ada</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        @if($guardian->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('guardians.update')
                                <button
                                    type="button"
                                    wire:click="issuePassword({{ $guardian->id }})"
                                    wire:confirm="Terbitkan password portal baru untuk {{ $guardian->name }}? Password lama tidak akan berlaku lagi."
                                    class="btn-icon"
                                    title="Terbitkan password portal"
                                    aria-label="Terbitkan password portal untuk {{ $guardian->name }}"
                                >
                                    <i data-lucide="key-round" class="w-4 h-4"></i>
                                </button>

                                <button
                                    type="button"
                                    wire:click="edit({{ $guardian->id }})"
                                    class="btn-icon"
                                    title="Ubah data orang tua"
                                    aria-label="Ubah data {{ $guardian->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('guardians.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $guardian->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus data orang tua"
                                    aria-label="Hapus data {{ $guardian->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$guardians" label="orang tua" />
    </div>

    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Data Orang Tua' : 'Tambah Orang Tua'"
        subtitle="Akun ini dipakai untuk masuk ke portal orang tua dengan nomor HP dan password."
        size="lg"
    >
        @if($generatedPassword)
            <div class="mb-4 rounded-[6px] border border-[#f0c36d] bg-[#fef8e7] px-4 py-3">
                <p class="text-[13px] font-semibold text-[#8a6100]">Password portal baru</p>
                <p class="mt-1 font-mono text-lg font-bold tracking-wider text-[#8a6100]">{{ $generatedPassword }}</p>
                <p class="mt-1 text-[12px] text-[#8a6100]">
                    Catat sekarang. Password tidak dapat ditampilkan lagi setelah modal ditutup.
                </p>
            </div>
        @endif

        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="name"
                    name="name"
                    label="Nama Orang Tua / Wali"
                    placeholder="Nama lengkap"
                    required
                    icon="user"
                />

                <x-ui.select
                    wire:model="relation"
                    name="relation"
                    label="Hubungan dengan Siswa"
                    :options="$relations"
                    required
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="phone"
                    name="phone"
                    label="Nomor HP / WhatsApp"
                    placeholder="Contoh: 081234567890"
                    hint="Dipakai sebagai username saat masuk portal."
                    required
                    icon="phone"
                />

                <x-ui.input
                    wire:model="email"
                    name="email"
                    type="email"
                    label="Email"
                    placeholder="opsional@email.com"
                    icon="mail"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="occupation"
                    name="occupation"
                    label="Pekerjaan"
                    placeholder="Contoh: Pedagang"
                    icon="briefcase"
                />

                <label for="guardian_is_active" class="flex items-start gap-2.5 cursor-pointer select-none pt-6">
                    <input
                        id="guardian_is_active"
                        type="checkbox"
                        wire:model="is_active"
                        class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                    >
                    <span>
                        <span class="block text-[13px] font-medium text-[#374151]">Akun aktif</span>
                        <span class="block text-[12px] text-[#8994a3]">Akun nonaktif tidak dapat masuk ke portal.</span>
                    </span>
                </label>
            </div>

            <x-ui.textarea
                wire:model="address"
                name="address"
                label="Alamat"
                hint="Opsional."
                rows="2"
            />

            <div>
                <label class="block text-[13px] font-medium text-[#374151] mb-1">Siswa yang Terhubung</label>
                <p class="text-[12px] text-[#8994a3] mb-2">
                    Pilih satu atau beberapa anak. Orang tua dengan lebih dari satu anak cukup memakai satu akun.
                </p>

                <div class="max-h-[220px] overflow-y-auto scrollbar-thin border border-[#e1e6ed] rounded-[6px] p-2 space-y-1">
                    @forelse($students as $studentId => $studentLabel)
                        <label for="student-{{ $studentId }}" class="flex items-center gap-2.5 rounded-[4px] px-2 py-1.5 hover:bg-[#f6f9fc] cursor-pointer">
                            <input
                                id="student-{{ $studentId }}"
                                type="checkbox"
                                value="{{ $studentId }}"
                                wire:model="selectedStudents"
                                class="w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                            >
                            <span class="text-[13px] text-[#374151]">{{ $studentLabel }}</span>
                        </label>
                    @empty
                        <p class="px-2 py-3 text-[12px] text-[#8994a3]">Belum ada data siswa yang dapat dipilih.</p>
                    @endforelse
                </div>

                @error('selectedStudents.*')
                    <span class="block text-[#c0392b] mt-1 text-[12px]">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Data' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="showDeleteModal" title="Hapus Data Orang Tua" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Akun orang tua akan dihapus dan tidak dapat masuk portal lagi. Data siswa tidak ikut terhapus. Lanjutkan?
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

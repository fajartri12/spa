@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Data Master'], ['label' => 'Petugas']]" />
@endsection

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Petugas</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola data petugas komite, bendahara, dan staf madrasah.
            </p>
        </div>

        @can('staff.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>Tambah Petugas</span>
            </x-ui.button>
        @endcan
    </div>

    {{-- Filter --}}
    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Petugas"
                    placeholder="Nama, NIP, telepon, atau email..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="positionFilter"
                name="positionFilter"
                label="Jabatan"
                placeholder="Semua Jabatan"
                :options="$positions"
            />

            <x-ui.select
                wire:model.live="unitFilter"
                name="unitFilter"
                label="Unit"
                placeholder="Semua Unit"
                :options="$units"
            />

            <x-ui.select
                wire:model.live="statusFilter"
                name="statusFilter"
                label="Status"
                placeholder="Semua Status"
                :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']"
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
        <x-ui.table :empty="$staffMembers->isEmpty()"
            empty-title="Belum Ada Data Petugas"
            empty-description="Tambahkan data petugas terlebih dahulu agar dapat ditugaskan pada proses pembayaran.">
            <x-slot:head>
                <tr>
                    <th>Nama Petugas</th>
                    <th class="w-32">NIP</th>
                    <th class="w-44">Jabatan</th>
                    <th class="w-40">Unit</th>
                    <th class="w-44">Kontak</th>
                    <th class="w-28">Status</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($staffMembers as $staff)
                <tr wire:key="staff-{{ $staff->id }}">
                    <td>
                        <div class="font-medium text-[#111827]">{{ $staff->name }}</div>
                        <div class="text-[12px] text-[#8994a3] mt-0.5">
                            {{ $staff->gender?->label() ?? '-' }}
                            @if($staff->user)
                                &middot; akun: {{ $staff->user->email }}
                            @endif
                        </div>
                    </td>
                    <td class="tabular text-[#475569]">{{ $staff->nip ?: '-' }}</td>
                    <td>
                        @if($staff->position)
                            <x-ui.badge variant="info">{{ $staff->position->label() }}</x-ui.badge>
                        @else
                            <span class="text-[#94a3b8]">-</span>
                        @endif
                    </td>
                    <td class="text-[#475569]">{{ $staff->unit?->name ?? '-' }}</td>
                    <td>
                        <div class="text-[13px] text-[#475569]">{{ $staff->phone ?: '-' }}</div>
                        @if($staff->email)
                            <div class="text-[12px] text-[#8994a3]">{{ $staff->email }}</div>
                        @endif
                    </td>
                    <td>
                        @if($staff->is_active)
                            <x-ui.badge variant="success" dot>Aktif</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral" dot>Nonaktif</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('staff.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $staff->id }})"
                                    class="btn-icon"
                                    title="Ubah petugas"
                                    aria-label="Ubah petugas {{ $staff->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('staff.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $staff->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus petugas"
                                    aria-label="Hapus petugas {{ $staff->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$staffMembers" label="petugas" />
    </div>

    {{-- Modal Form --}}
    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Data Petugas' : 'Tambah Petugas'"
        subtitle="Data petugas digunakan sebagai penanggung jawab transaksi pembayaran."
        size="lg"
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="name"
                    name="name"
                    label="Nama Lengkap"
                    placeholder="Nama petugas"
                    required
                    icon="user"
                />

                <x-ui.input
                    wire:model="nip"
                    name="nip"
                    label="NIP"
                    placeholder="Nomor induk pegawai"
                    hint="Opsional, harus unik."
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select
                    wire:model="gender"
                    name="gender"
                    label="Jenis Kelamin"
                    :options="$genders"
                    required
                />

                <x-ui.select
                    wire:model="position"
                    name="position"
                    label="Jabatan"
                    :options="$positions"
                    required
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="phone"
                    name="phone"
                    label="Nomor Telepon"
                    placeholder="08xxxxxxxxxx"
                />

                <x-ui.input
                    wire:model="email"
                    name="email"
                    type="email"
                    label="Email"
                    placeholder="nama@example.com"
                    icon="mail"
                />
            </div>

            <x-ui.select
                wire:model="unit_id"
                name="unit_id"
                label="Unit Penugasan"
                placeholder="Tanpa unit khusus"
                :options="$units"
                hint="Kosongkan jika bertugas lintas unit."
            />

            <x-ui.textarea
                wire:model="address"
                name="address"
                label="Alamat"
                placeholder="Alamat lengkap petugas"
                rows="2"
            />

            <x-ui.select
                wire:model="user_id"
                name="user_id"
                label="Tautkan Akun Pengguna"
                placeholder="Tidak ditautkan"
                :options="$users"
                hint="Opsional. Satu akun hanya boleh tertaut ke satu petugas."
            />

            <label for="staff_is_active" class="flex items-start gap-2.5 cursor-pointer select-none">
                <input
                    id="staff_is_active"
                    type="checkbox"
                    wire:model="is_active"
                    class="mt-0.5 w-4 h-4 rounded-[4px] border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]"
                >
                <span>
                    <span class="block text-[13px] font-medium text-[#374151]">Petugas aktif</span>
                    <span class="block text-[12px] text-[#8994a3]">Petugas nonaktif tidak dapat dipilih pada transaksi baru.</span>
                </span>
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">
                    Batal
                </x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Petugas' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    {{-- Modal Hapus --}}
    <x-ui.modal name="showDeleteModal" title="Hapus Petugas" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Data petugas akan dihapus. Riwayat transaksi yang pernah dibuat tetap tersimpan. Lanjutkan?
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

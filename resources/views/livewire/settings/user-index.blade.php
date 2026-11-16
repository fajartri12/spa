@section('breadcrumb')
    <x-ui.breadcrumb :items="[['label' => 'Pengaturan'], ['label' => 'Pengguna']]" />
@endsection

<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="page-title">Pengguna</h1>
            <p class="text-[13px] text-[#64748b] mt-1">
                Kelola akun petugas yang dapat masuk ke aplikasi beserta rolenya.
            </p>
        </div>

        @can('users.create')
            <x-ui.button variant="primary" wire:click="create">
                <i data-lucide="user-plus" class="w-4 h-4"></i>
                <span>Tambah Pengguna</span>
            </x-ui.button>
        @endcan
    </div>

    <div class="card px-4 py-3">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-ui.input
                    wire:model.live.debounce.300ms="search"
                    name="search"
                    label="Cari Pengguna"
                    placeholder="Nama, email, atau username..."
                    icon="search"
                />
            </div>

            <x-ui.select
                wire:model.live="roleFilter"
                name="roleFilter"
                label="Role"
                placeholder="Semua Role"
                :options="$roles"
            />

            <div class="flex items-end gap-2">
                <x-ui.select
                    wire:model.live="statusFilter"
                    name="statusFilter"
                    label="Status"
                    placeholder="Semua Status"
                    :options="$statuses"
                    class="flex-1"
                />
                <button type="button" wire:click="resetFilters" class="btn btn-secondary" title="Reset filter">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden" wire:loading.class="opacity-60">
        <x-ui.table :empty="$users->isEmpty()"
            empty-title="Belum Ada Pengguna"
            empty-description="Tambahkan akun pengguna untuk memberikan akses aplikasi kepada petugas.">
            <x-slot:head>
                <tr>
                    <th>Nama</th>
                    <th class="w-56">Email / Username</th>
                    <th class="w-48">Role</th>
                    <th class="w-32">Status</th>
                    <th class="w-40">Terakhir Masuk</th>
                    <th class="w-24 text-right">Aksi</th>
                </tr>
            </x-slot:head>

            @foreach($users as $user)
                <tr wire:key="user-{{ $user->id }}">
                    <td>
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-[#eaf2fc] text-[#20599f] text-[12px] font-semibold flex items-center justify-center shrink-0">
                                {{ $user->initials }}
                            </span>
                            <div>
                                <div class="font-medium text-[#111827]">{{ $user->name }}</div>
                                @if($user->phone)
                                    <div class="text-[12px] text-[#8994a3]">{{ $user->phone }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="text-[#374151]">{{ $user->email }}</div>
                        @if($user->username)
                            <div class="text-[12px] text-[#8994a3] font-mono">{{ $user->username }}</div>
                        @endif
                    </td>
                    <td>
                        @forelse($user->roles as $role)
                            <x-ui.badge variant="purple">{{ $roleLabels[$role->name] ?? $role->name }}</x-ui.badge>
                        @empty
                            <span class="text-[12px] text-[#8994a3]">Tanpa role</span>
                        @endforelse
                    </td>
                    <td>
                        <x-ui.badge :variant="$user->status->badgeVariant()" dot>{{ $user->status->label() }}</x-ui.badge>
                    </td>
                    <td class="text-[#475569]">
                        {{ $user->last_login_at?->translatedFormat('d M Y H:i') ?? '-' }}
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-1">
                            @can('users.update')
                                <button
                                    type="button"
                                    wire:click="edit({{ $user->id }})"
                                    class="btn-icon"
                                    title="Ubah pengguna"
                                    aria-label="Ubah pengguna {{ $user->name }}"
                                >
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>
                            @endcan

                            @can('users.delete')
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $user->id }})"
                                    class="btn-icon hover:!bg-[#fdedec] hover:!text-[#c0392b]"
                                    title="Hapus pengguna"
                                    aria-label="Hapus pengguna {{ $user->name }}"
                                >
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <x-ui.pagination :paginator="$users" label="pengguna" />
    </div>

    <x-ui.modal
        name="showModal"
        :title="$editingId ? 'Ubah Pengguna' : 'Tambah Pengguna'"
        subtitle="Akun pengguna menentukan akses modul melalui role yang dipilih."
    >
        <form wire:submit="save" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="name" name="name" label="Nama Lengkap" required icon="user" />
                <x-ui.input wire:model="username" name="username" label="Username" hint="Opsional, untuk login alternatif." icon="at-sign" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input wire:model="email" name="email" type="email" label="Email" required icon="mail" />
                <x-ui.input wire:model="phone" name="phone" label="Nomor WhatsApp" icon="phone" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select wire:model="role" name="role" label="Role" placeholder="Pilih role" :options="$roles" required />
                <x-ui.select wire:model="status" name="status" label="Status Akun" :options="$statuses" required />
            </div>

            <x-ui.input
                wire:model="password"
                name="password"
                type="password"
                :label="$editingId ? 'Kata Sandi Baru' : 'Kata Sandi'"
                :hint="$editingId ? 'Biarkan kosong jika tidak ingin mengubah kata sandi.' : 'Minimal 8 karakter.'"
                :required="! $editingId"
                icon="lock"
            />

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-[#eef2f7]">
                <x-ui.button variant="secondary" type="button" wire:click="$set('showModal', false)">Batal</x-ui.button>
                <x-ui.button variant="primary" type="submit" loading="save">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>{{ $editingId ? 'Simpan Perubahan' : 'Simpan Pengguna' }}</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal name="showDeleteModal" title="Hapus Pengguna" size="sm">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-full bg-[#fdedec] flex items-center justify-center shrink-0">
                <i data-lucide="triangle-alert" class="w-5 h-5 text-[#c0392b]"></i>
            </div>
            <p class="text-[13px] text-[#64748b] leading-relaxed">
                Akun pengguna akan dihapus dan tidak dapat masuk lagi ke aplikasi. Riwayat aktivitas tetap tersimpan. Lanjutkan?
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

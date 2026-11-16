@php
    use App\Support\Formatter;
@endphp

<div class="max-w-4xl space-y-6">
    <div>
        <h1 class="page-title">Profil Saya</h1>
        <p class="text-[13px] text-[#64748b] mt-1">
            Kelola data akun dan kata sandi Anda.
        </p>
    </div>

    {{-- Info Akun --}}
    <x-ui.card title="Informasi Pengguna" subtitle="Perbarui nama, email, dan nomor telepon akun Anda.">
        <form wire:submit="updateProfile" class="space-y-4" novalidate>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="name"
                    name="name"
                    label="Nama Lengkap"
                    required
                    icon="user"
                />

                <x-ui.input
                    wire:model="email"
                    name="email"
                    type="email"
                    label="Alamat Email"
                    required
                    icon="mail"
                />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input
                    wire:model="username"
                    name="username"
                    label="Username"
                    hint="Opsional, hanya huruf, angka, tanda minus (-), dan garis bawah (_)."
                    icon="user"
                />

                <x-ui.input
                    wire:model="phone"
                    name="phone"
                    label="Nomor WhatsApp / HP"
                    placeholder="081234567890"
                    icon="message-circle"
                />
            </div>

            <div class="p-3 bg-[#f8fafc] border border-[#e1e6ed] rounded-[6px] text-[12px] text-[#64748b] flex flex-wrap gap-x-6 gap-y-2">
                <div>
                    <span class="text-[#8994a3]">Role:</span>
                    <span class="font-medium text-[#374151]">{{ $user->primaryRoleLabel() }}</span>
                </div>
                <div>
                    <span class="text-[#8994a3]">Status:</span>
                    <x-ui.badge variant="success" dot>{{ ucfirst($user->status->value) }}</x-ui.badge>
                </div>
                <div>
                    <span class="text-[#8994a3]">Terdaftar sejak:</span>
                    <span class="tabular text-[#374151]">{{ Formatter::date($user->created_at) }}</span>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <x-ui.button type="submit" variant="primary" loading="updateProfile">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Simpan Perubahan</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{-- Ubah Password --}}
    <x-ui.card title="Ubah Kata Sandi" subtitle="Gunakan kata sandi yang aman dan tidak mudah ditebak.">
        <form wire:submit="updatePassword" class="space-y-4 max-w-xl" novalidate>
            <x-ui.input
                wire:model="current_password"
                name="current_password"
                type="password"
                label="Kata Sandi Saat Ini"
                required
                icon="lock"
            />

            <x-ui.input
                wire:model="password"
                name="password"
                type="password"
                label="Kata Sandi Baru"
                hint="Minimal 8 karakter."
                required
                icon="key-round"
            />

            <x-ui.input
                wire:model="password_confirmation"
                name="password_confirmation"
                type="password"
                label="Konfirmasi Kata Sandi Baru"
                required
                icon="key-round"
            />

            <div class="flex justify-end pt-2">
                <x-ui.button type="submit" variant="primary" loading="updatePassword">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span>Ubah Kata Sandi</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
</div>

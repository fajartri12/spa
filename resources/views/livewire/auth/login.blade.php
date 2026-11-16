<div>
    <h2 class="text-[22px] font-semibold text-[#111827] leading-tight">Masuk ke Aplikasi</h2>
    <p class="text-sm text-[#64748b] mt-1.5 mb-7">
        Gunakan akun yang telah diberikan oleh administrator.
    </p>

    <form wire:submit="login" class="space-y-4" novalidate>
        <x-ui.input
            wire:model="email"
            name="email"
            type="email"
            label="Alamat Email"
            placeholder="nama@man7jombang.sch.id"
            autocomplete="username"
            autofocus
            required
            icon="mail"
        />

        <x-ui.input
            wire:model="password"
            name="password"
            type="password"
            label="Kata Sandi"
            placeholder="Masukkan kata sandi"
            autocomplete="current-password"
            required
            icon="lock"
        />

        <label for="remember" class="inline-flex items-center gap-2 cursor-pointer select-none pt-1">
            <input
                id="remember"
                type="checkbox"
                wire:model="remember"
                class="w-4 h-4 rounded border-[#cbd5e1] text-[#2872c8] focus:ring-[#2872c8]/30"
            >
            <span class="text-[13px] text-[#475569]">Ingat saya</span>
        </label>

        <x-ui.button type="submit" variant="primary" size="lg" class="w-full" wire:loading.attr="disabled">
            <i data-lucide="log-in" class="w-4 h-4" wire:loading.remove wire:target="login"></i>
            <span wire:loading.remove wire:target="login">Masuk</span>
            <i data-lucide="loader-circle" class="w-4 h-4 animate-spin" wire:loading wire:target="login"></i>
            <span wire:loading wire:target="login">Memproses...</span>
        </x-ui.button>
    </form>

    <p class="mt-6 text-center text-[12px] text-[#8994a3]">
        Lupa kata sandi? Hubungi administrator madrasah.
    </p>

    <p class="mt-3 text-center text-[12px] text-[#8994a3]">
        <a href="{{ route('ppdb.register') }}" class="text-[#2872c8] hover:underline">
            Pendaftaran PPDB Online
        </a>
    </p>
</div>

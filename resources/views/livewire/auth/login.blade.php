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
            placeholder="nama@email.com"
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

    @if($demoAccounts->isNotEmpty())
        <details class="mt-6 border-t border-[#e5eaf0] pt-4">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-medium text-[#334155]">
                <span class="inline-flex items-center gap-2">
                    <i data-lucide="users-round" class="h-4 w-4 text-[#2872c8]"></i>
                    Akun Demo
                </span>
                <i data-lucide="chevron-down" class="h-4 w-4 text-[#64748b]"></i>
            </summary>

            <div class="mt-3 divide-y divide-[#eef2f7] rounded-[6px] border border-[#e5eaf0]">
                @foreach($demoAccounts as $account)
                    <button
                        type="button"
                        wire:click="useDemoAccount('{{ $account->email }}')"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left transition hover:bg-[#f8fafc] focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[#2872c8]"
                    >
                        <span class="min-w-0">
                            <span class="block text-[12px] font-semibold text-[#334155]">{{ $account->role }}</span>
                            <span class="block break-all text-[11px] text-[#64748b]">{{ $account->email }}</span>
                        </span>
                        <span class="shrink-0 text-[11px] text-[#64748b]">password</span>
                    </button>
                @endforeach
            </div>
            <p class="mt-2 text-[11px] text-[#8994a3]">Pilih akun untuk mengisi formulir masuk.</p>
        </details>
    @endif
</div>

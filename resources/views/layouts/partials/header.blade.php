@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $schoolName = \App\Models\AppSetting::get('school_name', 'MAN 7 JOMBANG');
@endphp

<header class="app-header bg-[#2872c8] text-white">
    <div class="px-3.5 sm:px-5 lg:px-6 h-16 lg:h-20 flex items-center justify-between gap-4">

        {{-- Identitas aplikasi --}}
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
            <span class="w-10 h-10 lg:w-12 lg:h-12 rounded-full bg-white/15 border border-white/25 flex items-center justify-center shrink-0">
                <i data-lucide="school" class="w-5 h-5 lg:w-6 lg:h-6"></i>
            </span>
            <span class="min-w-0">
                <span class="block text-[15px] lg:text-[19px] font-semibold leading-tight truncate">
                    KOMITE {{ $schoolName }}
                </span>
                <span class="hidden sm:block text-[10px] lg:text-[11px] tracking-[0.08em] uppercase text-white/75 leading-tight mt-0.5">
                    Aplikasi Pengelolaan Pembayaran Tagihan
                </span>
            </span>
        </a>

        {{-- Profil pengguna --}}
        @if($authUser)
            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open"
                    aria-haspopup="menu"
                    class="flex items-center gap-2.5 rounded-[5px] px-1.5 py-1.5 hover:bg-white/10 transition"
                >
                    <span class="hidden text-right sm:block">
                        <span class="block text-[13px] font-medium leading-tight">{{ $authUser->name }}</span>
                        <span class="block text-[11px] text-white/70 leading-tight">{{ $authUser->primaryRoleLabel() }}</span>
                    </span>

                    @if($authUser->avatar_url)
                        <img src="{{ $authUser->avatar_url }}" alt="Foto {{ $authUser->name }}"
                             class="w-9 h-9 rounded-full object-cover border border-white/30">
                    @else
                        <span class="w-9 h-9 rounded-full bg-white/20 border border-white/30 flex items-center justify-center text-[13px] font-semibold">
                            {{ $authUser->initials }}
                        </span>
                    @endif

                    <i data-lucide="chevron-down" class="w-4 h-4 text-white/80 hidden sm:block"></i>
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 -translate-y-1"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="absolute right-0 mt-2 w-60 bg-white text-[#374151] rounded-[6px] shadow-[0_4px_16px_-2px_rgb(16_24_40/0.12)] border border-[#e1e6ed] py-1 z-50"
                    role="menu"
                >
                    <div class="px-3.5 py-2.5 border-b border-[#eef2f7]">
                        <p class="text-[13px] font-semibold text-[#111827] truncate">{{ $authUser->name }}</p>
                        <p class="text-[12px] text-[#8994a3] truncate">{{ $authUser->email }}</p>
                    </div>

                    <a href="{{ route('profile.edit') }}" class="nav-dropdown-item" role="menuitem">
                        <i data-lucide="user" class="w-4 h-4"></i>
                        <span>Profil Saya</span>
                    </a>

                    @if(Route::has('settings.index'))
                        @can('settings.view')
                            <a href="{{ route('settings.index') }}" class="nav-dropdown-item" role="menuitem">
                                <i data-lucide="settings" class="w-4 h-4"></i>
                                <span>Pengaturan</span>
                            </a>
                        @endcan
                    @endif

                    <div class="h-px bg-[#eef2f7] my-1"></div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="nav-dropdown-item w-full text-left text-[#c0392b] hover:!bg-[#fdedec] hover:!text-[#c0392b]" role="menuitem">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            <span>Keluar</span>
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</header>

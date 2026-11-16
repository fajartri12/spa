@php
    use App\Support\Navigation;
    use App\Models\AppSetting;

    $menu = Navigation::forUser();
    $current = request()->route()?->getName();
    $activeLabel = 'Beranda';
    $navSticky = (bool) AppSetting::get('theme_nav_sticky', true);

    foreach ($menu as $item) {
        if (Navigation::isActive($item)) {
            $activeLabel = $item['label'];
            break;
        }
    }
@endphp

<nav @class(['app-nav bg-white border-b border-[#e1e6ed]', 'sticky top-0 z-30' => $navSticky]) aria-label="Navigasi utama">
    <div class="px-3.5 sm:px-5 lg:px-6">
        {{-- Desktop --}}
        <div class="hidden lg:flex items-center gap-0.5">
            @foreach($menu as $item)
                @php $active = Navigation::isActive($item); @endphp

                @if(empty($item['children']))
                    <a
                        href="{{ route($item['route']) }}"
                        @class(['nav-link', 'is-active' => $active])
                        @if($active) aria-current="page" @endif
                    >
                        <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @else
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                        <button
                            type="button"
                            @class(['nav-link', 'is-active' => $active])
                            @click="open = !open"
                            :aria-expanded="open.toString()"
                            aria-haspopup="true"
                        >
                            <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                            <span>{{ $item['label'] }}</span>
                            <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform duration-150" :class="open && 'rotate-180'"></i>
                        </button>

                        <div
                            x-show="open"
                            x-cloak
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="absolute left-0 top-full w-64 bg-white border border-[#e1e6ed] rounded-[6px] shadow-[0_4px_16px_-2px_rgb(16_24_40/0.12)] py-1 z-40"
                            role="menu"
                        >
                            @foreach($item['children'] as $child)
                                @if(! empty($child['heading']))
                                    <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-[#94a3b8]">{{ $child['heading'] }}</p>
                                    @continue
                                @endif

                                @php
                                    $childActive = $current === ($child['route'] ?? null);
                                    foreach ($child['params'] ?? [] as $paramKey => $paramValue) {
                                        $childActive = $childActive && (string) request()->route()?->parameter($paramKey) === (string) $paramValue;
                                    }
                                @endphp

                                @if(! empty($child['children']))
                                    @php
                                        $groupActive = $childActive;
                                        foreach ($child['children'] as $grandchild) {
                                            $match = $current === ($grandchild['route'] ?? null);
                                            foreach ($grandchild['params'] ?? [] as $paramKey => $paramValue) {
                                                $match = $match && (string) request()->route()?->parameter($paramKey) === (string) $paramValue;
                                            }
                                            $groupActive = $groupActive || $match;
                                        }
                                    @endphp
                                    <div class="relative" x-data="{ sub: false }" @mouseenter="sub = true" @mouseleave="sub = false">
                                        <button
                                            type="button"
                                            @click="sub = !sub"
                                            @class(['nav-dropdown-item w-full justify-between', 'is-active' => $groupActive])
                                        >
                                            <span class="flex items-center gap-2.5">
                                                <i data-lucide="{{ $child['icon'] }}" class="w-4 h-4"></i>
                                                <span>{{ $child['label'] }}</span>
                                            </span>
                                            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                        </button>

                                        <div
                                            x-show="sub"
                                            x-cloak
                                            class="absolute left-full top-0 -mt-1 ml-1 w-60 bg-white border border-[#e1e6ed] rounded-[6px] shadow-[0_4px_16px_-2px_rgb(16_24_40/0.12)] py-1 z-50"
                                            role="menu"
                                        >
                                            @foreach($child['children'] as $grandchild)
                                                @php
                                                    $grandActive = $current === ($grandchild['route'] ?? null);
                                                    foreach ($grandchild['params'] ?? [] as $paramKey => $paramValue) {
                                                        $grandActive = $grandActive && (string) request()->route()?->parameter($paramKey) === (string) $paramValue;
                                                    }
                                                @endphp
                                                <a
                                                    href="{{ route($grandchild['route'], $grandchild['params'] ?? []) }}"
                                                    @class(['nav-dropdown-item', 'is-active' => $grandActive])
                                                    @if($grandActive) aria-current="page" @endif
                                                    role="menuitem"
                                                >
                                                    <i data-lucide="{{ $grandchild['icon'] }}" class="w-4 h-4"></i>
                                                    <span>{{ $grandchild['label'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <a
                                        href="{{ route($child['route'], $child['params'] ?? []) }}"
                                        @class(['nav-dropdown-item', 'is-active' => $childActive])
                                        @if($childActive) aria-current="page" @endif
                                        role="menuitem"
                                    >
                                        <i data-lucide="{{ $child['icon'] }}" class="w-4 h-4"></i>
                                        <span>{{ $child['label'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Mobile --}}
        <div class="lg:hidden" x-data="{ open: false }" @keydown.escape.window="open = false">
            <div class="flex items-center justify-between h-14">
                <span class="text-[13px] font-semibold text-[#111827]">{{ $activeLabel }}</span>
                <button type="button" @click="open = true" class="btn-icon" aria-label="Buka menu navigasi">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
            </div>

            <div x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Menu navigasi">
                <div class="absolute inset-0 bg-[#0f172a]/50" @click="open = false"></div>

                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="-translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    class="relative h-full w-72 max-w-[calc(100%-3rem)] bg-white shadow-xl flex flex-col"
                >
                    <div class="flex items-center justify-between px-4 h-14 border-b border-[#e1e6ed]">
                        <span class="font-semibold text-[#111827]">Menu</span>
                        <button type="button" @click="open = false" class="btn-icon" aria-label="Tutup menu">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto py-2">
                        @foreach($menu as $item)
                            @php $active = Navigation::isActive($item); @endphp

                            @if(empty($item['children']))
                                <a href="{{ route($item['route']) }}" @class(['nav-dropdown-item', 'is-active' => $active])>
                                    <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @else
                                <div x-data="{ sub: {{ $active ? 'true' : 'false' }} }">
                                    <button type="button" @click="sub = !sub" @class(['nav-dropdown-item w-full justify-between', 'is-active' => $active])>
                                        <span class="flex items-center gap-2.5">
                                            <i data-lucide="{{ $item['icon'] }}" class="w-4 h-4"></i>
                                            <span>{{ $item['label'] }}</span>
                                        </span>
                                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" :class="sub && 'rotate-180'"></i>
                                    </button>

                                    <div x-show="sub" x-collapse class="ml-5 pl-3 border-l border-[#eef2f7] my-1">
                                        @foreach($item['children'] as $child)
                                            @if(! empty($child['heading']))
                                                <p class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-[#94a3b8]">{{ $child['heading'] }}</p>
                                                @continue
                                            @endif

                                            @php
                                                $childActive = $current === ($child['route'] ?? null);
                                                foreach ($child['params'] ?? [] as $paramKey => $paramValue) {
                                                    $childActive = $childActive && (string) request()->route()?->parameter($paramKey) === (string) $paramValue;
                                                }
                                            @endphp

                                            @if(! empty($child['children']))
                                                @php
                                                    $groupActive = $childActive;
                                                    foreach ($child['children'] as $grandchild) {
                                                        $match = $current === ($grandchild['route'] ?? null);
                                                        foreach ($grandchild['params'] ?? [] as $paramKey => $paramValue) {
                                                            $match = $match && (string) request()->route()?->parameter($paramKey) === (string) $paramValue;
                                                        }
                                                        $groupActive = $groupActive || $match;
                                                    }
                                                @endphp
                                                <div x-data="{ openGroup: {{ $groupActive ? 'true' : 'false' }} }">
                                                    <button type="button" @click="openGroup = !openGroup" @class(['nav-dropdown-item w-full justify-between', 'is-active' => $groupActive])>
                                                        <span class="flex items-center gap-2.5">
                                                            <i data-lucide="{{ $child['icon'] }}" class="w-4 h-4"></i>
                                                            <span>{{ $child['label'] }}</span>
                                                        </span>
                                                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 transition-transform" :class="openGroup && 'rotate-180'"></i>
                                                    </button>
                                                    <div x-show="openGroup" x-collapse class="ml-4 pl-2 border-l border-[#eef2f7] my-1">
                                                        @foreach($child['children'] as $grandchild)
                                                            @php
                                                                $grandActive = $current === ($grandchild['route'] ?? null);
                                                                foreach ($grandchild['params'] ?? [] as $paramKey => $paramValue) {
                                                                    $grandActive = $grandActive && (string) request()->route()?->parameter($paramKey) === (string) $paramValue;
                                                                }
                                                            @endphp
                                                            <a href="{{ route($grandchild['route'], $grandchild['params'] ?? []) }}" @class(['nav-dropdown-item', 'is-active' => $grandActive])>
                                                                <i data-lucide="{{ $grandchild['icon'] }}" class="w-4 h-4"></i>
                                                                <span>{{ $grandchild['label'] }}</span>
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @else
                                                <a href="{{ route($child['route'], $child['params'] ?? []) }}" @class(['nav-dropdown-item', 'is-active' => $childActive])>
                                                    <i data-lucide="{{ $child['icon'] }}" class="w-4 h-4"></i>
                                                    <span>{{ $child['label'] }}</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

@props([
    'align' => 'left', // left | right
    'width' => 'w-56',
    'trigger' => null,
])

@php
    $alignment = $align === 'right' ? 'right-0' : 'left-0';
@endphp

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" class="relative">
    <div @click="open = !open" :aria-expanded="open" aria-haspopup="menu">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        @click="open = false"
        class="absolute {{ $alignment }} mt-2 {{ $width }} bg-white border border-[#e1e6ed] rounded-[6px] shadow-[0_4px_16px_-2px_rgb(16_24_40/0.12)] py-1 z-40 overflow-hidden"
        role="menu"
    >
        {{ $slot }}
    </div>
</div>

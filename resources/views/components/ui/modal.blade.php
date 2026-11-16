@props([
    'name' => null,
    'title' => null,
    'subtitle' => null,
    'size' => 'md', // sm | md | lg
    'footer' => null,
])

@php
    $maxWidth = match ($size) {
        'sm' => 'max-w-[400px]',
        'lg' => 'max-w-[800px]',
        default => 'max-w-[600px]',
    };
@endphp

<div
    x-data="{ open: @entangle($name) }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6"
    role="dialog"
    aria-modal="true"
    @if($title) aria-label="{{ $title }}" @endif
>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="absolute inset-0 bg-[#0f172a]/50"
    ></div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        {{ $attributes->merge(['class' => "relative w-full {$maxWidth} max-h-[90vh] flex flex-col bg-white rounded-[8px] shadow-[0_12px_32px_-8px_rgb(16_24_40/0.24)]"]) }}
    >
        @if($title)
            <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-[#e1e6ed]">
                <div>
                    <h2 class="text-[16px] font-semibold text-gray-900">{{ $title }}</h2>
                    @if($subtitle)
                        <p class="text-[12px] text-[#8994a3] mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
                <button
                    type="button"
                    @click="open = false"
                    class="btn-icon -mr-1"
                    aria-label="Tutup dialog"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
        @endif

        <div class="px-5 py-4 overflow-y-auto flex-1">
            {{ $slot }}
        </div>

        @if($footer)
            <div class="px-5 py-3 border-t border-[#e1e6ed] flex items-center justify-end gap-2">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>

@props([
    'type' => 'info', // success | warning | danger | info
    'title' => null,
    'dismissible' => false,
])

@php
    $config = match ($type) {
        'success' => [
            'bg' => 'bg-[#edf7e5]',
            'border' => 'border-[#d4ecc0]',
            'text' => 'text-[#2b6413]',
            'icon' => 'circle-check',
            'iconColor' => 'text-[#438a21]',
        ],
        'warning' => [
            'bg' => 'bg-[#fff5e5]',
            'border' => 'border-[#fedfa8]',
            'text' => 'text-[#7d4e06]',
            'icon' => 'triangle-alert',
            'iconColor' => 'text-[#b9770e]',
        ],
        'danger' => [
            'bg' => 'bg-[#fdedec]',
            'border' => 'border-[#fad3d0]',
            'text' => 'text-[#872015]',
            'icon' => 'circle-alert',
            'iconColor' => 'text-[#c0392b]',
        ],
        default => [
            'bg' => 'bg-[#eaf2fc]',
            'border' => 'border-[#c5dcf7]',
            'text' => 'text-[#15467e]',
            'icon' => 'info',
            'iconColor' => 'text-[#2872c8]',
        ],
    };
@endphp

<div
    x-data="{ show: true }"
    x-show="show"
    x-transition
    {{ $attributes->merge(['class' => "p-3.5 rounded-[5px] border {$config['bg']} {$config['border']} {$config['text']} flex items-start gap-3 text-[13px]"]) }}
    role="alert"
>
    <i data-lucide="{{ $config['icon'] }}" class="w-5 h-5 shrink-0 {{ $config['iconColor'] }} mt-0.5"></i>
    <div class="flex-1">
        @if($title)
            <div class="font-semibold mb-0.5">{{ $title }}</div>
        @endif
        <div class="leading-relaxed">{{ $slot }}</div>
    </div>
    @if($dismissible)
        <button
            type="button"
            @click="show = false"
            class="text-gray-400 hover:text-gray-700 transition"
            aria-label="Tutup"
        >
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    @endif
</div>

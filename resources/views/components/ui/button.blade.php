@props([
    'variant' => 'primary', // primary | secondary | success | danger | ghost
    'size' => 'md',        // sm | md | lg
    'type' => 'button',
    'icon' => null,
    'iconRight' => null,
    'loading' => false,
])

@php
    $baseClasses = 'btn';
    $variantClasses = match ($variant) {
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        'ghost' => 'btn-ghost',
        default => 'btn-primary',
    };
    $sizeClasses = match ($size) {
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };
    $classes = trim("{$baseClasses} {$variantClasses} {$sizeClasses}");
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $classes]) }}
    @if($loading) wire:loading.attr="disabled" @endif
>
    @if($icon)
        <i data-lucide="{{ $icon }}" class="w-4 h-4" @if($loading) wire:loading.remove @endif></i>
    @endif

    @if($loading)
        <i data-lucide="loader-circle" class="w-4 h-4 animate-spin" wire:loading></i>
    @endif

    <span class="inline-flex items-center gap-2">{{ $slot }}</span>

    @if($iconRight)
        <i data-lucide="{{ $iconRight }}" class="w-4 h-4"></i>
    @endif
</button>

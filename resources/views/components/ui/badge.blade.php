@props([
    'variant' => 'neutral', // success | warning | danger | info | primary | neutral | purple
    'dot' => false,
])

@php
    $variantClasses = match ($variant) {
        'success' => 'badge-success',
        'warning' => 'badge-warning',
        'danger' => 'badge-danger',
        'info' => 'badge-info',
        'primary' => 'badge-primary',
        'purple' => 'badge-purple',
        default => 'badge-neutral',
    };
    $dotClasses = match ($variant) {
        'success' => 'bg-[#438a21]',
        'warning' => 'bg-[#b9770e]',
        'danger' => 'bg-[#c0392b]',
        'info' => 'bg-[#1c6a9e]',
        'primary' => 'bg-[#20599f]',
        'purple' => 'bg-[#7c3fa0]',
        default => 'bg-[#64748b]',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge {$variantClasses}"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $dotClasses }}"></span>
    @endif
    {{ $slot }}
</span>

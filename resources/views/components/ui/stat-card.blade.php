@props([
    'label',
    'value',
    'icon' => 'wallet',
    'tone' => 'primary', // primary | primary-dark | success | danger | info | warning | purple | orange
    'hint' => null,
])

@php
    $toneClass = match ($tone) {
        'primary-dark' => 'stat-card-primary-dark',
        'success' => 'stat-card-success',
        'danger' => 'stat-card-danger',
        'info' => 'stat-card-info',
        'warning' => 'stat-card-warning',
        'purple' => 'stat-card-purple',
        'orange' => 'stat-card-orange',
        default => 'stat-card-primary',
    };
@endphp

<div {{ $attributes->merge(['class' => "stat-card {$toneClass}"]) }}>
    <div class="relative z-10 min-w-0">
        <p class="stat-card-label">{{ $label }}</p>
        <p class="stat-card-value">{{ $value }}</p>
        @if($hint)
            <p class="stat-card-hint">{{ $hint }}</p>
        @endif
    </div>

    <i data-lucide="{{ $icon }}" class="stat-card-watermark" aria-hidden="true"></i>
</div>

@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'hint' => null,
    'required' => false,
    'icon' => null,
    'prefix' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $name && $errors->has($name);
    $errorMessage = $hasError ? $errors->first($name) : null;
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'w-full']) }}>
    @if($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if($required)<span class="text-[#c0392b]" aria-hidden="true">*</span>@endif
        </label>
    @endif

    @if($icon || $prefix)
        <div class="relative">
            @if($icon)
                <i data-lucide="{{ $icon }}" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#8994a3] pointer-events-none"></i>
            @endif
            @if($prefix)
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[13px] text-[#64748b]">{{ $prefix }}</span>
            @endif
            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="{{ $type }}"
                @if($required) required @endif
                @if($hasError) aria-invalid="true" @endif
                {{ $attributes->except(['class', 'id', 'name', 'type'])->merge([
                    'class' => 'form-input ' . ($icon ? 'pl-9' : '') . ($prefix ? 'pl-10' : '') . ($hasError ? ' border-[#e74c3c]' : ''),
                ]) }}
            >
        </div>
    @else
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if($required) required @endif
            @if($hasError) aria-invalid="true" @endif
            {{ $attributes->except(['class', 'id', 'name', 'type'])->merge([
                'class' => 'form-input' . ($hasError ? ' border-[#e74c3c]' : ''),
            ]) }}
        >
    @endif

    @if($errorMessage)
        <p class="form-error">{{ $errorMessage }}</p>
    @elseif($hint)
        <p class="form-help">{{ $hint }}</p>
    @endif
</div>

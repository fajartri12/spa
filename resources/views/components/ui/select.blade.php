@props([
    'name' => null,
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id') ?? $name;
    $hasError = $name && $errors->has($name);
    $errorMessage = $hasError ? $errors->first($name) : null;
    $current = $selected ?? ($name ? old($name) : null);
    $options = is_array($options) ? $options : $options->toArray();
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'w-full']) }}>
    @if($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if($required)<span class="text-[#c0392b]" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if($required) required @endif
        @if($hasError) aria-invalid="true" @endif
        {{ $attributes->except(['class', 'id', 'name'])->merge([
            'class' => 'form-select' . ($hasError ? ' border-[#e74c3c]' : ''),
        ]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $value => $labelOption)
            <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $labelOption }}</option>
        @endforeach

        {{ $slot ?? '' }}
    </select>

    @if($errorMessage)
        <p class="form-error">{{ $errorMessage }}</p>
    @elseif($hint)
        <p class="form-help">{{ $hint }}</p>
    @endif
</div>

@props([
    'name' => null,
    'label' => null,
    'hint' => null,
    'required' => false,
    'rows' => 4,
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

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if($required) required @endif
        @if($hasError) aria-invalid="true" @endif
        {{ $attributes->except(['class', 'id', 'name'])->merge([
            'class' => 'form-textarea' . ($hasError ? ' border-[#e74c3c]' : ''),
        ]) }}
    >{{ $slot }}</textarea>

    @if($errorMessage)
        <p class="form-error">{{ $errorMessage }}</p>
    @elseif($hint)
        <p class="form-help">{{ $hint }}</p>
    @endif
</div>

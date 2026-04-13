@props([
    'name',
    'id' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $fieldId = $id ?? 'field_' . preg_replace('/[\[\]]+/', '_', trim($name, '[]'));
    $hasError = filled($error);
    $displayValue = $value !== null ? $value : old($name);
    $uppercaseDisplay = in_array($type, ['text', 'email'], true);
@endphp

<div class="kmsar-form-group">
    @if ($label !== null)
        <label for="{{ $fieldId }}" class="kmsar-form-label">
            {{ $label }}
            @if ($required)
                <span class="kmsar-form-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        value="{{ $displayValue }}"
        @if ($required) required @endif
        @if ($uppercaseDisplay)
            style="text-transform: uppercase"
        @endif
        {{ $attributes->class(['kmsar-input', $hasError ? 'kmsar-input--error' : '']) }}
    />

    @if ($hint !== null && $hint !== '' && ! $hasError)
        <p class="kmsar-form-hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="kmsar-form-error" id="{{ $fieldId }}-error" role="alert">{{ $error }}</p>
    @endif
</div>

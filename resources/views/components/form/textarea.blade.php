@props([
    'name',
    'id' => null,
    'label' => null,
    'rows' => null,
    'value' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $fieldId = $id ?? 'field_' . preg_replace('/[\[\]]+/', '_', trim($name, '[]'));
    $hasError = filled($error);
    $body = $slot->isEmpty() ? old($name, $value ?? '') : $slot;
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

    <textarea
        name="{{ $name }}"
        id="{{ $fieldId }}"
        style="text-transform: uppercase"
        @if ($rows !== null) rows="{{ $rows }}" @endif
        @if ($required) required @endif
        {{ $attributes->class(['kmsar-textarea', $hasError ? 'kmsar-input--error' : '']) }}
    >{{ $body }}</textarea>

    @if ($hint !== null && $hint !== '' && ! $hasError)
        <p class="kmsar-form-hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="kmsar-form-error" id="{{ $fieldId }}-error" role="alert">{{ $error }}</p>
    @endif
</div>

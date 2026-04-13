@props([
    'name',
    'id' => null,
    'label' => null,
    'options' => [],
    'placeholder' => null,
    'selected' => null,
    'value' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $fieldId = $id ?? 'field_' . preg_replace('/[\[\]]+/', '_', trim($name, '[]'));
    $hasError = filled($error);
    $current = $value !== null ? $value : ($selected !== null ? $selected : old($name));
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

    <select
        name="{{ $name }}"
        id="{{ $fieldId }}"
        @if ($required) required @endif
        {{ $attributes->class(['kmsar-select', $hasError ? 'kmsar-input--error' : '']) }}
    >
        @if ($slot->isEmpty())
            @if ($placeholder !== null)
                <option value="" @selected($current === null || $current === '')>{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                @if (is_array($optionLabel))
                    <optgroup label="{{ $optionValue }}">
                        @foreach ($optionLabel as $subValue => $subLabel)
                            <option value="{{ $subValue }}" @selected((string) $current === (string) $subValue)>
                                {{ $subLabel }}
                            </option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                        {{ $optionLabel }}
                    </option>
                @endif
            @endforeach
        @else
            {{ $slot }}
        @endif
    </select>

    @if ($hint !== null && $hint !== '' && ! $hasError)
        <p class="kmsar-form-hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="kmsar-form-error" id="{{ $fieldId }}-error" role="alert">{{ $error }}</p>
    @endif
</div>

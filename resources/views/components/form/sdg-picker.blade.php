{{--
  Stores JSON array of selected SDG numbers (1–17) in a single hidden input.
  Decode in the controller: json_decode($request->input('sdg_tags'), true) ?? []
--}}
@props([
    'name' => 'sdg_tags',
    'label' => 'SDG alignment',
    'hint' => 'Select one or more Sustainable Development Goals that apply.',
    'error' => null,
    'required' => false,
    'selected' => [],
])

@php
    $hasError = filled($error);
    $fieldId = 'sdg_' . preg_replace('/[^\w]+/', '_', $name);
    $initial = array_values(array_map('intval', $selected ?? []));
@endphp

<div
    class="kmsar-form-group"
    x-data="{
        selected: @js($initial),
        toggle(n) {
            const i = this.selected.indexOf(n);
            if (i > -1) {
                this.selected.splice(i, 1);
            } else {
                this.selected.push(n);
            }
            this.selected.sort((a, b) => a - b);
        },
        isOn(n) {
            return this.selected.includes(n);
        },
    }"
>
    @if ($label !== null)
        <span id="{{ $fieldId }}-label" class="kmsar-form-label">
            {{ $label }}
            @if ($required)
                <span class="kmsar-form-required" aria-hidden="true">*</span>
            @endif
        </span>
    @endif

    <div
        class="kmsar-sdg-grid"
        role="group"
        @if ($label !== null)
            aria-labelledby="{{ $fieldId }}-label"
        @else
            aria-label="Sustainable Development Goals (1–17)"
        @endif
    >
        @foreach (range(1, 17) as $sdg)
            <button
                type="button"
                class="kmsar-sdg-chip"
                :class="{ 'selected': isOn({{ $sdg }}) }"
                :aria-pressed="isOn({{ $sdg }}) ? 'true' : 'false'"
                @click="toggle({{ $sdg }})"
            >
                SDG {{ $sdg }}
            </button>
        @endforeach
    </div>

    <input type="hidden" name="{{ $name }}" x-ref="sdgInput" x-effect="$refs.sdgInput.value = JSON.stringify(selected)">

    @if ($hint !== null && $hint !== '' && ! $hasError)
        <p class="kmsar-form-hint" id="{{ $fieldId }}-hint">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="kmsar-form-error" id="{{ $fieldId }}-error" role="alert">{{ $error }}</p>
    @endif
</div>

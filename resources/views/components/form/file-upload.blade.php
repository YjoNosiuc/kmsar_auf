@props([
    'name',
    'label' => null,
    'accept' => null,
    'multiple' => false,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $fieldId = 'file_' . preg_replace('/[^\w]+/', '_', $name);
    $hasError = filled($error);
    $inputName = $multiple ? $name . '[]' : $name;
@endphp

<div
    class="kmsar-form-group"
    x-data="{
        dragging: false,
        multiple: @js($multiple),
        names: '',
        assignFiles(fileList) {
            const input = this.$refs.fileInput;
            const dt = new DataTransfer();
            const files = Array.from(fileList);
            if (this.multiple) {
                files.forEach((f) => dt.items.add(f));
            } else if (files.length) {
                dt.items.add(files[0]);
            }
            input.files = dt.files;
            this.names = Array.from(input.files).map((f) => f.name).join(', ');
        },
    }"
>
    @if ($label !== null)
        <label for="{{ $fieldId }}" class="kmsar-form-label">
            {{ $label }}
            @if ($required)
                <span class="kmsar-form-required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div
        class="kmsar-dropzone {{ $hasError ? 'kmsar-input--error' : '' }}"
        :class="{ 'kmsar-dropzone--drag': dragging }"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="dragging = false; assignFiles($event.dataTransfer.files)"
        @click="$refs.fileInput.click()"
        role="button"
        tabindex="0"
        aria-describedby="{{ $fieldId }}-hint-text"
        @keydown.enter.prevent="$refs.fileInput.click()"
        @keydown.space.prevent="$refs.fileInput.click()"
    >
        <svg class="kmsar-dropzone-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <p class="kmsar-dropzone-title"><span>Choose {{ $multiple ? 'files' : 'a file' }}</span> or drag here</p>
        <p id="{{ $fieldId }}-hint-text" class="kmsar-dropzone-hint">
            <span x-show="!names">{{ $hint ?? 'Allowed types depend on server validation (e.g. PDF, DOCX).' }}</span>
            <span x-show="names" class="kmsar-file-name block mt-1"><span x-text="'Selected: ' + names"></span></span>
        </p>
    </div>

    <input
        type="file"
        name="{{ $inputName }}"
        id="{{ $fieldId }}"
        class="hidden"
        x-ref="fileInput"
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($multiple) multiple @endif
        @if ($required) required @endif
        @change="assignFiles($event.target.files)"
        {{ $attributes }}
    />

    @if ($hasError)
        <p class="kmsar-form-error" id="{{ $fieldId }}-error" role="alert">{{ $error }}</p>
    @endif
</div>

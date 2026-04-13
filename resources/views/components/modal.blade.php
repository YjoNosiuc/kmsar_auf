{{--
  Place inside a parent Alpine scope, e.g. <div x-data="{ open: false }">, and pass:
    x-show="open" @click.self="open = false" x-cloak style="display: none;"
  Listen for the close control: @close-kmsar-modal.window="open = false"
  Footer buttons may also use @click="open = false".
--}}
@props([
    'title' => '',
    'titleDanger' => false,
    'size' => null,
    'id' => null,
])

@php
    $titleId = $id ? $id . '-title' : 'kmsar-modal-title-' . uniqid();

    $modalBoxClass = 'kmsar-modal';
    if ($size === 'lg') {
        $modalBoxClass .= ' kmsar-modal--lg';
    } elseif ($size === 'sm') {
        $modalBoxClass .= ' kmsar-modal--sm';
    }
@endphp

<div
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
    {{ $attributes->class(['kmsar-modal-overlay']) }}
>
    <div class="{{ $modalBoxClass }}" @click.stop>
        <div class="kmsar-modal-header">
            <h2 id="{{ $titleId }}" class="kmsar-modal-title {{ $titleDanger ? 'kmsar-modal-title--danger' : '' }}">
                {{ $title }}
            </h2>
            <button
                type="button"
                class="kmsar-modal-close"
                aria-label="Close dialog"
                @click="$dispatch('close-kmsar-modal')"
            >&times;</button>
        </div>
        <div class="kmsar-modal-body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="kmsar-modal-footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>

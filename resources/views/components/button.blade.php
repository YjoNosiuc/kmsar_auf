@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'kmsar-btn--secondary',
        'outline' => 'kmsar-btn--outline',
        'gold' => 'kmsar-btn--gold',
        'success' => 'kmsar-btn--success',
        'warning' => 'kmsar-btn--warning',
        'danger' => 'kmsar-btn--danger',
        'danger-outline' => 'kmsar-btn--danger-outline',
        'ghost' => 'kmsar-btn--ghost',
        default => 'kmsar-btn--primary',
    };

    $sizeClass = match ($size) {
        'xs' => 'kmsar-btn--xs',
        'sm' => 'kmsar-btn--sm',
        'lg' => 'kmsar-btn--lg',
        default => 'kmsar-btn--md',
    };
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        {{ $attributes->class(['kmsar-btn', $variantClass, $sizeClass]) }}
    >{{ $slot }}</a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class(['kmsar-btn', $variantClass, $sizeClass]) }}
    >{{ $slot }}</button>
@endif

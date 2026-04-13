@props([
    'label' => '',
    'value' => '—',
    'color' => 'primary',
    'delta' => null,
])

@php
    $valueClass = match ($color) {
        'pending' => 'kmsar-stat-card-value kmsar-stat-card-value--pending',
        'approved' => 'kmsar-stat-card-value kmsar-stat-card-value--approved',
        'revision' => 'kmsar-stat-card-value kmsar-stat-card-value--revision',
        'danger' => 'kmsar-stat-card-value kmsar-stat-card-value--danger',
        default => 'kmsar-stat-card-value',
    };

    $deltaClass = 'kmsar-stat-card-delta';
    if (is_string($delta) && str_starts_with($delta, '-')) {
        $deltaClass .= ' kmsar-stat-card-delta--down';
    }
@endphp

<div {{ $attributes->class(['kmsar-stat-card']) }}>
    <div class="kmsar-stat-card-label">{{ $label }}</div>
    <div class="{{ $valueClass }}">{{ $value }}</div>
    @if ($delta !== null && $delta !== '')
        <div class="{{ $deltaClass }}">{{ $delta }}</div>
    @endif
</div>

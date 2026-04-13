@props([
    'status' => 'draft',
    'square' => false,
])

@php
    $statusClass = match ($status) {
        'approved' => 'kmsar-badge--approved',
        'returned', 'revision' => 'kmsar-badge--revision',
        'pending' => 'kmsar-badge--pending',
        'rejected' => 'kmsar-badge--rejected',
        'draft' => 'kmsar-badge--draft',
        'info' => 'kmsar-badge--info',
        'gold' => 'kmsar-badge--gold',
        'solid-primary' => 'kmsar-badge--solid-primary',
        default => 'kmsar-badge--draft',
    };

    $extra = $square ? ['kmsar-badge--square'] : [];
@endphp

<span {{ $attributes->class(array_merge(['kmsar-badge', $statusClass], $extra)) }}>
    {{ $slot }}
</span>

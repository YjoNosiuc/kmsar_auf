@props([
    'title' => null,
    'count' => null,
    'accent' => null,
    'hoverable' => false,
])

@php
    $accentClass = match ($accent) {
        'primary' => 'kmsar-card--accent-primary',
        'gold' => 'kmsar-card--accent-gold',
        'success' => 'kmsar-card--accent-success',
        'pending' => 'kmsar-card--accent-pending',
        'danger' => 'kmsar-card--accent-danger',
        'revision' => 'kmsar-card--accent-revision',
        default => '',
    };

    $cardClasses = array_filter([
        'kmsar-card',
        $accentClass ?: null,
        $hoverable ? 'kmsar-card--hoverable' : null,
    ]);
@endphp

<div {{ $attributes->class($cardClasses) }}>
    @if ($title !== null || isset($actions) || $count !== null)
        <div class="kmsar-card-header">
            <div>
                @if ($title !== null)
                    <h3 class="kmsar-card-title">{{ $title }}</h3>
                @endif
                @if ($count !== null)
                    <span class="kmsar-hint mt-1 block">{{ $count }} {{ \Illuminate\Support\Str::plural('item', (int) $count) }}</span>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 flex-shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="kmsar-card-body">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="kmsar-card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>

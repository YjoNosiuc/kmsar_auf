@props([
    'items' => [],
])

@php
    $normalized = [];
    foreach ($items as $item) {
        if (is_object($item)) {
            $item = (array) $item;
        }
        $normalized[] = [
            'title' => $item['title'] ?? '',
            'meta' => $item['meta'] ?? null,
            'remark' => $item['remark'] ?? null,
            'state' => $item['state'] ?? $item['dot'] ?? 'pending',
        ];
    }
@endphp

<div {{ $attributes->class(['kmsar-timeline']) }}>
    @foreach ($normalized as $item)
        @php
            $dotClass = match ($item['state']) {
                'done', 'completed' => 'kmsar-timeline-dot kmsar-timeline-dot--done',
                'active', 'current' => 'kmsar-timeline-dot kmsar-timeline-dot--active',
                default => 'kmsar-timeline-dot kmsar-timeline-dot--pending',
            };
        @endphp
        <div class="kmsar-timeline-item">
            <div class="{{ $dotClass }}" aria-hidden="true">
                @if (in_array($item['state'], ['done', 'completed'], true))
                    <span>✓</span>
                @endif
            </div>
            <div class="kmsar-timeline-content">
                <div class="kmsar-timeline-title">{{ $item['title'] }}</div>
                @if ($item['meta'])
                    <div class="kmsar-timeline-meta">{{ $item['meta'] }}</div>
                @endif
                @if ($item['remark'])
                    <div class="kmsar-timeline-remark">{{ $item['remark'] }}</div>
                @endif
            </div>
        </div>
    @endforeach

    @if (count($normalized) === 0)
        <div class="kmsar-body">
            @if ($slot->isEmpty())
                No timeline entries.
            @else
                {{ $slot }}
            @endif
        </div>
    @endif
</div>

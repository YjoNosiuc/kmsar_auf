@props([
    'headers' => [],
    'rows' => [],
    'empty' => 'No records found.',
    'rowClass' => null,
])

@php
    /** @var array<int|string, string> $columns key => header label */
    $columns = [];
    foreach ($headers as $key => $label) {
        $columns[$key] = $label;
    }
@endphp

<div {{ $attributes->class(['kmsar-table-wrap']) }}>
    <table class="kmsar-table">
        <thead>
            <tr>
                @foreach ($columns as $colKey => $colLabel)
                    <th scope="col">{{ $colLabel }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $trClass = '';
                    if ($rowClass !== null) {
                        $trClass = is_callable($rowClass) ? (string) ($rowClass)($row) : (string) $rowClass;
                    }
                @endphp
                <tr @class([$trClass !== '' ? $trClass : null])>
                    @foreach ($columns as $colKey => $_)
                        @php
                            $cell = data_get($row, $colKey);
                            $isActions = $colKey === 'actions';
                        @endphp
                        <td @class([
                            'whitespace-nowrap' => $isActions,
                        ])>
                            @if ($cell instanceof \Illuminate\Contracts\Support\Htmlable)
                                {!! $cell->toHtml() !!}
                            @else
                                {{ $cell }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" class="kmsar-body" style="padding: var(--space-6);">
                        {{ $empty }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator && $rows->hasPages())
    <div class="mt-4 flex justify-end">
        {{ $rows->links() }}
    </div>
@endif

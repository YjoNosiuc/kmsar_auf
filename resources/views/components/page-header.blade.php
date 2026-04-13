@props([
    'title' => '',
    'subtitle' => null,
    'breadcrumb' => [],
])

<div {{ $attributes->class(['kmsar-page-header']) }}>
    <div class="flex-1 min-w-0">
        @if (count($breadcrumb))
            <nav class="kmsar-breadcrumb" aria-label="Breadcrumb">
                @foreach ($breadcrumb as $crumb)
                    @php
                        $label = $crumb['label'] ?? $crumb['name'] ?? '';
                        $routeName = $crumb['route'] ?? null;
                        $url = $crumb['url'] ?? null;
                        $href = null;
                        if ($routeName && Route::has($routeName)) {
                            $href = route($routeName, $crumb['parameters'] ?? []);
                        } elseif ($url) {
                            $href = $url;
                        }
                        $isLast = $loop->last;
                    @endphp
                    @if (! $isLast)
                        @if ($href)
                            <a href="{{ $href }}" class="kmsar-breadcrumb-link">{{ $label }}</a>
                        @else
                            <span class="kmsar-breadcrumb-link" style="opacity: 0.65; cursor: default;">{{ $label }}</span>
                        @endif
                        <span class="kmsar-breadcrumb-sep" aria-hidden="true">/</span>
                    @else
                        <span class="kmsar-breadcrumb-current" aria-current="page">{{ $label }}</span>
                    @endif
                @endforeach
            </nav>
        @endif

        <h1 class="kmsar-h1">{{ $title }}</h1>
        @if ($subtitle)
            <p class="kmsar-body mt-2">{{ $subtitle }}</p>
        @endif
    </div>

    @if (! $slot->isEmpty() || (isset($actions) && ! $actions->isEmpty()))
        <div class="kmsar-page-header-actions">
            @if (! $slot->isEmpty())
                {{ $slot }}
            @endif
            @isset($actions)
                {{ $actions }}
            @endisset
        </div>
    @endif
</div>

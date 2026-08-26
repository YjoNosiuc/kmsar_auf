@extends('layouts.app')

@push('scripts-head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
@endpush

@section('title', __('College Dashboard — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('College') }}
@endsection

@section('content')
    {{-- Section 1 — Page header + year filter --}}
    <div class="kmsar-page-header">
        <div class="flex-1 min-w-0">
            <h1 class="kmsar-h1">{{ __('College Dashboard') }}</h1>
            <p class="kmsar-body mt-2">
                @if (! empty($scopeAllColleges))
                    {{ __('All Colleges') }}
                @elseif ($college)
                    {{ $college->name }}
                @else
                    {{ __('No college assigned to your account.') }}
                @endif
            </p>
        </div>
    </div>

    @if ($college || ! empty($scopeAllColleges))
        <form method="get" action="{{ route('dean.dashboard') }}" class="kmsar-card" style="margin-bottom:16px;padding:16px 20px;">
            <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <div>
                    <label for="date_from" style="font-size:12px; font-weight:600; color:#64748B; display:block; margin-bottom:4px;">
                        {{ __('Research accepted from') }}
                    </label>
                    <input type="date"
                           id="date_from"
                           name="date_from"
                           value="{{ $dateFrom }}"
                           class="kmsar-input"
                           style="width:160px;">
                </div>
                <div>
                    <label for="date_to" style="font-size:12px; font-weight:600; color:#64748B; display:block; margin-bottom:4px;">
                        {{ __('Research accepted to') }}
                    </label>
                    <input type="date"
                           id="date_to"
                           name="date_to"
                           value="{{ $dateTo }}"
                           class="kmsar-input"
                           style="width:160px;">
                </div>
                <div>
                    <button type="submit" class="kmsar-btn kmsar-btn--primary" style="padding:8px 20px;">
                        {{ __('Apply') }}
                    </button>
                    @if ($dateFrom || $dateTo)
                        <a href="{{ route('dean.dashboard') }}"
                           class="kmsar-btn kmsar-btn--outline"
                           style="padding:8px 20px; margin-left:8px;">
                            {{ __('Clear') }}
                        </a>
                    @endif
                </div>
            </div>
        </form>
    @endif

    {{-- Section 2 — Stat cards --}}
    <div class="kmsar-stats-grid kmsar-stats-grid--5 kmsar-animate-in mb-8" role="region" aria-label="{{ __('College research statistics') }}">
        <div class="kmsar-stat-card kmsar-card--accent-primary">
            <div class="kmsar-stat-card-label">{{ __('Total Research') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($totalResearch) }}</div>
        </div>
        <div class="kmsar-stat-card" data-stat-card="in-progress">
            <div class="kmsar-stat-card-label">{{ __('Research In Progress') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($researchInProgress ?? 0) }}</div>
            <div class="kmsar-stat-card-sub">{{ __('Research registered') }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-pending">
            <div class="kmsar-stat-card-label">{{ __('Pending Endorsement') }}</div>
            <div class="kmsar-stat-card-value kmsar-stat-card-value--pending">{{ number_format($pendingEndorsement) }}</div>
        </div>
        <div class="kmsar-stat-card">
            <div class="kmsar-stat-card-label">{{ __('Presented') }}</div>
            <div class="kmsar-stat-card-value" style="color:#2563EB;">{{ number_format($presentedCount) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-gold">
            <div class="kmsar-stat-card-label">{{ __('Scopus/WoS Indexed') }}</div>
            <div class="kmsar-stat-card-value" style="color: var(--color-gold);">{{ number_format($scopusIndexedCount) }}</div>
        </div>
    </div>

    @if ($college || ! empty($scopeAllColleges))
        {{-- Section 3 — Submitted line chart + faculty table --}}
        <div class="kmsar-dashboard-chart-grid kmsar-dashboard-chart-grid--3-2" style="margin-bottom:16px;">
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">
                            @if ($dateFrom || $dateTo)
                                {{ __('Research accepted — selected dates') }}
                            @else
                                {{ __('Research accepted — last 5 years') }}
                            @endif
                        </h2>
                        <p class="kmsar-chart-subtitle">{{ __('Count of research accepted per year (your college)') }}</p>
                    </div>
                </div>
                <div class="kmsar-chart-body">
                    <div class="kmsar-chart-canvas-wrap">
                        <canvas id="kmsarDeanSubmitted" aria-label="{{ __('Research accepted by year') }}"></canvas>
                    </div>
                </div>
            </div>
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">{{ __('Research per faculty') }}</h2>
                        <p class="kmsar-chart-subtitle">{{ __('Totals where the faculty member is primary author or listed co-author') }}</p>
                    </div>
                    <div style="min-width:200px;">
                        <label for="facultySearch" class="sr-only">{{ __('Search faculty') }}</label>
                        <input
                            id="facultySearch"
                            type="search"
                            class="kmsar-input"
                            style="width:100%;"
                            placeholder="{{ __('Search faculty…') }}"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="kmsar-chart-body" style="padding: 0;">
                    <div class="kmsar-table-wrap" style="font-size:0.8125rem;">
                        <table class="kmsar-table" id="facultyStatsTable">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Faculty name') }}</th>
                                    <th scope="col" style="text-align:center;">{{ __('Total research') }}</th>
                                    <th scope="col" style="text-align:center;">{{ __('Published') }}</th>
                                    <th scope="col" style="text-align:center;">{{ __('Presented') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($facultyStats as $row)
                                    <tr data-faculty-name="{{ strtolower($row['name']) }}">
                                        <td>{{ $row['name'] }}</td>
                                        <td style="text-align:center;">{{ number_format($row['total']) }}</td>
                                        <td style="text-align:center;">{{ number_format($row['published']) }}</td>
                                        <td style="text-align:center;">{{ number_format($row['presented']) }}</td>
                                    </tr>
                                @empty
                                    <tr id="facultyStatsEmpty">
                                        <td colspan="4" class="text-center kmsar-body" style="padding: var(--space-6);">
                                            {{ __('No faculty in this college yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 4 — Published + Presented bar charts --}}
        <div class="kmsar-dashboard-chart-grid kmsar-dashboard-chart-grid--2" style="margin-bottom:16px;">
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">
                            @if ($dateFrom || $dateTo)
                                {{ __('Published research — selected dates') }}
                            @else
                                {{ __('Published research — last 5 years') }}
                            @endif
                        </h2>
                        <p class="kmsar-chart-subtitle">{{ __('Published status counts by year') }}</p>
                    </div>
                </div>
                <div class="kmsar-chart-body">
                    <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--short">
                        <canvas id="kmsarDeanPublished" aria-label="{{ __('Published research by year') }}"></canvas>
                    </div>
                </div>
            </div>
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">
                            @if ($dateFrom || $dateTo)
                                {{ __('Presented research — selected dates') }}
                            @else
                                {{ __('Presented research — last 5 years') }}
                            @endif
                        </h2>
                        <p class="kmsar-chart-subtitle">{{ __('Internal and external presentations by year') }}</p>
                    </div>
                </div>
                <div class="kmsar-chart-body">
                    <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--short">
                        <canvas id="kmsarDeanPresented" aria-label="{{ __('Presented research by year') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Agenda theme alignment (research accepted only) --}}
        <div class="kmsar-chart-card" style="margin-bottom:16px;">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('AUF Research Agenda theme alignment') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Accepted research aligned to institutional agenda themes') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--compact" style="max-width:520px;margin:0 auto;">
                    <canvas id="kmsarDeanAgendaThemes" aria-label="{{ __('Agenda theme alignment') }}"></canvas>
                </div>
                @if (($agendaThemeBreakdown ?? collect())->sum('count') > 0)
                    <div class="kmsar-chart-legend" style="margin-top:12px;font-size:0.75rem;line-height:1.35;">
                        @foreach ($agendaThemeBreakdown as $idx => $row)
                            @if ($row['count'] > 0)
                                <div class="kmsar-legend-item">
                                    <span class="kmsar-legend-dot" style="background:{{ ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#D97706', '#7C3AED'][$idx] ?? '#94A3B8' }};"></span>
                                    <span>{{ $row['label'] }}</span>
                                    <span class="kmsar-legend-value">{{ number_format($row['count']) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    @php
        use App\Support\ResearchStatus;

        $statusBadgeVariant = static function (string $status): string {
            return match ($status) {
                ResearchStatus::DRAFT => 'draft',
                ResearchStatus::INITIAL_DEAN_REVIEW, ResearchStatus::FINAL_DEAN_REVIEW => 'pending',
                ResearchStatus::INITIAL_OVPRI_REVIEW, ResearchStatus::FINAL_OVPRI_REVIEW => 'info',
                ResearchStatus::RESEARCH_REGISTERED, ResearchStatus::RESEARCH_ACCEPTED => 'approved',
                ResearchStatus::INITIAL_REJECTED, ResearchStatus::FINAL_REJECTED => 'rejected',
                ResearchStatus::RESEARCH_COMPLETED => 'info',
                default => 'info',
            };
        };
    @endphp

    {{-- Section 5 — Recent research --}}
    <div class="kmsar-card kmsar-card--accent-primary">
        <div class="kmsar-card-header">
            <div>
                <h2 class="kmsar-card-title">{{ __('Recent research') }}</h2>
                <p class="kmsar-body mt-1" style="color: var(--color-text-secondary); font-size: 0.875rem;">
                    {{ __('Latest research accepted for your college (including affiliations), newest first.') }}
                </p>
            </div>
        </div>
        <div class="kmsar-card-body">
            <div class="kmsar-table-wrap">
                <table class="kmsar-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Reference') }}</th>
                            <th scope="col">{{ __('Title') }}</th>
                            <th scope="col">{{ __('Author') }}</th>
                            <th scope="col">{{ __('Workflow status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentResearch as $item)
                            <tr>
                                <td>
                                    @if (in_array($item->status, [ResearchStatus::INITIAL_DEAN_REVIEW, ResearchStatus::FINAL_DEAN_REVIEW], true))
                                        <a href="{{ route('approval.review', $item) }}" class="kmsar-link font-medium">{{ $item->reference_number }}</a>
                                    @elseif ($item->status === ResearchStatus::DRAFT)
                                        <span class="font-medium" style="color: var(--color-text-muted);">{{ $item->reference_number }}</span>
                                        <div class="kmsar-body" style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 2px;">{{ __('Not yet submitted') }}</div>
                                    @else
                                        <span class="font-medium">{{ $item->reference_number }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="kmsar-table-cell-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</span>
                                </td>
                                <td>{{ $item->primaryAuthor?->name ?? '—' }}</td>
                                <td>
                                    <x-badge :status="$statusBadgeVariant((string) $item->status)">
                                        {{ ResearchStatus::label($item->status) }}
                                    </x-badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center kmsar-body" style="padding: var(--space-6);">
                                    {{ __('No research records for your college yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if ($college || ! empty($scopeAllColleges))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const facultySearchEl = document.getElementById('facultySearch');
                const facultyTable = document.getElementById('facultyStatsTable');

                if (facultySearchEl && facultyTable) {
                    facultySearchEl.addEventListener('input', function () {
                        const q = this.value.trim().toLowerCase();
                        let visible = 0;
                        facultyTable.querySelectorAll('tbody tr[data-faculty-name]').forEach(function (row) {
                            const matches = !q || row.getAttribute('data-faculty-name').includes(q);
                            row.style.display = matches ? '' : 'none';
                            if (matches) {
                                visible++;
                            }
                        });
                    });
                }

                if (typeof Chart === 'undefined') {
                    return;
                }

                const yearLabels = @json($submissionsByYear->pluck('year'));
                const submissionsData = @json($submissionsByYear->pluck('count'));
                const publishedData = @json($publishedByYear->pluck('count'));
                const presentedData = @json($presentedByYear->pluck('count'));

                const primary = '#1E3A8A';
                const gold = '#D4AF37';
                const success = '#059669';

                const submittedEl = document.getElementById('kmsarDeanSubmitted');
                if (submittedEl) {
                    new Chart(submittedEl, {
                        type: 'line',
                        data: {
                            labels: yearLabels,
                            datasets: [{
                                label: @json(__('Research accepted')),
                                data: submissionsData,
                                borderColor: primary,
                                backgroundColor: 'rgba(30, 58, 138, 0.08)',
                                fill: true,
                                tension: 0.25,
                                pointBackgroundColor: primary,
                                pointBorderColor: primary,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: true, position: 'bottom' },
                            },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                                x: {},
                            },
                        },
                    });
                }

                const publishedEl = document.getElementById('kmsarDeanPublished');
                if (publishedEl) {
                    new Chart(publishedEl, {
                        type: 'bar',
                        data: {
                            labels: yearLabels,
                            datasets: [{
                                label: @json(__('Published')),
                                data: publishedData,
                                backgroundColor: gold,
                                borderColor: gold,
                                borderWidth: 1,
                                borderRadius: 4,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                            },
                            scales: {
                                x: { ticks: { maxRotation: 0 } },
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                            },
                        },
                    });
                }

                const presentedEl = document.getElementById('kmsarDeanPresented');
                if (presentedEl) {
                    new Chart(presentedEl, {
                        type: 'bar',
                        data: {
                            labels: yearLabels,
                            datasets: [{
                                label: @json(__('Presented')),
                                data: presentedData,
                                backgroundColor: success,
                                borderColor: success,
                                borderWidth: 1,
                                borderRadius: 4,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                            },
                            scales: {
                                x: { ticks: { maxRotation: 0 } },
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                            },
                        },
                    });
                }

                const agendaLabels = @json(collect($agendaThemeBreakdown ?? [])->pluck('label'));
                const agendaCounts = @json(collect($agendaThemeBreakdown ?? [])->pluck('count'));
                const agendaColors = ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#D97706', '#7C3AED'];
                const agendaEl = document.getElementById('kmsarDeanAgendaThemes');
                if (agendaEl && agendaCounts.some(function (n) { return n > 0; })) {
                    new Chart(agendaEl, {
                        type: 'pie',
                        data: {
                            labels: agendaLabels,
                            datasets: [{
                                data: agendaCounts,
                                backgroundColor: agendaLabels.map(function (_, i) { return agendaColors[i] ?? '#94A3B8'; }),
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: { padding: 8 },
                            plugins: {
                                legend: { display: false },
                            },
                        },
                    });
                }
            });
        </script>
    @endif

    <script>
        // Auto-refresh page every 2 minutes to keep counts current
        setTimeout(function () {
            window.location.reload();
        }, 120000);
    </script>
@endpush

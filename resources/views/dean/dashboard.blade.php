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
                @if ($college)
                    {{ $college->name }}
                @else
                    {{ __('No college assigned to your account.') }}
                @endif
            </p>
        </div>
    </div>

    @if ($college)
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <label style="font-size:13px;color:#475569;font-weight:500;">Show last</label>
            <input
                type="number"
                id="yearRangeInput"
                min="1"
                max="10"
                value="5"
                style="width:70px;padding:6px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:13px;text-align:center;font-family:inherit;"
                onchange="updateCharts(this.value)"
            >
            <label style="font-size:13px;color:#475569;font-weight:500;">years</label>
        </div>
    @endif

    {{-- Section 2 — Stat cards --}}
    <div class="kmsar-stats-grid kmsar-animate-in mb-8" role="region" aria-label="{{ __('College research statistics') }}">
        <div class="kmsar-stat-card kmsar-card--accent-primary">
            <div class="kmsar-stat-card-label">{{ __('Total Research') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($totalResearch) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-pending">
            <div class="kmsar-stat-card-label">{{ __('Pending Endorsement') }}</div>
            <div class="kmsar-stat-card-value kmsar-stat-card-value--pending">{{ number_format($pendingEndorsement) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-success">
            <div class="kmsar-stat-card-label">{{ __('Published') }}</div>
            <div class="kmsar-stat-card-value kmsar-stat-card-value--approved">{{ number_format($publishedCount) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-gold">
            <div class="kmsar-stat-card-label">{{ __('Scopus Indexed') }}</div>
            <div class="kmsar-stat-card-value" style="color: var(--color-gold);">{{ number_format($scopusIndexedCount) }}</div>
        </div>
    </div>

    @if ($college)
        {{-- Section 3 — Submitted line chart + faculty table --}}
        <div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:16px;">
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">{{ __('Research submitted — last N years') }}</h2>
                        <p class="kmsar-chart-subtitle">{{ __('Count of research registered per year (your college)') }}</p>
                    </div>
                </div>
                <div class="kmsar-chart-body">
                    <div style="position:relative;height:260px;width:100%;">
                        <canvas id="kmsarDeanSubmitted" aria-label="{{ __('Research submitted by year') }}"></canvas>
                    </div>
                </div>
            </div>
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">{{ __('Research per faculty') }}</h2>
                        <p class="kmsar-chart-subtitle">{{ __('Totals where the faculty member is primary author or listed author') }}</p>
                    </div>
                </div>
                <div class="kmsar-chart-body" style="padding: 0;">
                    <div class="kmsar-table-wrap" style="font-size:0.8125rem;">
                        <table class="kmsar-table">
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
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        <td style="text-align:center;">{{ number_format($row['total']) }}</td>
                                        <td style="text-align:center;">{{ number_format($row['published']) }}</td>
                                        <td style="text-align:center;">{{ number_format($row['presented']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
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
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">{{ __('Published research — last N years') }}</h2>
                        <p class="kmsar-chart-subtitle">{{ __('Published status counts by year') }}</p>
                    </div>
                </div>
                <div class="kmsar-chart-body">
                    <div style="position:relative;height:220px;width:100%;">
                        <canvas id="kmsarDeanPublished" aria-label="{{ __('Published research by year') }}"></canvas>
                    </div>
                </div>
            </div>
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">{{ __('Presented research — last N years') }}</h2>
                        <p class="kmsar-chart-subtitle">{{ __('Internal and external presentations by year') }}</p>
                    </div>
                </div>
                <div class="kmsar-chart-body">
                    <div style="position:relative;height:220px;width:100%;">
                        <canvas id="kmsarDeanPresented" aria-label="{{ __('Presented research by year') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @php
        $researchProgressBadgeStatus = static function (string $status): string {
            return match ($status) {
                'proposal', 'ongoing', 'patent_submitted' => 'pending',
                'published_scopus', 'published_non_indexed', 'patent_granted', 'completed_unpublished' => 'approved',
                'presented_internal', 'presented_external' => 'info',
                default => 'info',
            };
        };
        $approvalStageBadgeStatus = static function (string $stage): string {
            return match ($stage) {
                'draft' => 'draft',
                'dean_review', 'ovpri_review' => 'pending',
                'approved' => 'approved',
                'rejected' => 'rejected',
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
                    {{ __('Latest updates from your college, newest first.') }}
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
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Approval stage') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentResearch as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('approval.review', $item) }}" class="kmsar-link font-medium">{{ $item->reference_number }}</a>
                                </td>
                                <td>
                                    <span class="kmsar-table-cell-title">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</span>
                                </td>
                                <td>{{ $item->primaryAuthor?->name ?? '—' }}</td>
                                <td>
                                    <x-badge :status="$researchProgressBadgeStatus($item->status)">
                                        {{ str_replace('_', ' ', $item->status) }}
                                    </x-badge>
                                </td>
                                <td>
                                    <x-badge :status="$approvalStageBadgeStatus($item->approval_stage)">
                                        {{ str_replace('_', ' ', $item->approval_stage) }}
                                    </x-badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center kmsar-body" style="padding: var(--space-6);">
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
    @if ($college)
        <script>
            let submissionsChart;
            let publishedChart;
            let presentedChart;
            let allYearLabels;
            let allSubmissionsData;
            let allPublishedData;
            let allPresentedData;

            function updateCharts(years) {
                if (typeof Chart === 'undefined') {
                    return;
                }
                const currentYear = new Date().getFullYear();
                const n = Math.min(10, Math.max(1, parseInt(years, 10) || 5));
                const cutoff = currentYear - n;
                const filteredLabels = [];
                const filteredSubmissions = [];
                const filteredPublished = [];
                const filteredPresented = [];
                allYearLabels.forEach(function (lbl, i) {
                    const y = parseInt(lbl, 10);
                    if (!isNaN(y) && y > cutoff) {
                        filteredLabels.push(lbl);
                        filteredSubmissions.push(allSubmissionsData[i]);
                        filteredPublished.push(allPublishedData[i]);
                        filteredPresented.push(allPresentedData[i]);
                    }
                });
                if (submissionsChart) {
                    submissionsChart.data.labels = filteredLabels;
                    submissionsChart.data.datasets[0].data = filteredSubmissions;
                    submissionsChart.update();
                }
                if (publishedChart) {
                    publishedChart.data.labels = filteredLabels;
                    publishedChart.data.datasets[0].data = filteredPublished;
                    publishedChart.update();
                }
                if (presentedChart) {
                    presentedChart.data.labels = filteredLabels;
                    presentedChart.data.datasets[0].data = filteredPresented;
                    presentedChart.update();
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Chart === 'undefined') {
                    return;
                }

                const yearLabels = @json($submissionsByYear->pluck('year'));
                const submissionsData = @json($submissionsByYear->pluck('count'));
                const publishedData = @json($publishedByYear->pluck('count'));
                const presentedData = @json($presentedByYear->pluck('count'));

                allYearLabels = yearLabels.slice();
                allSubmissionsData = submissionsData.slice();
                allPublishedData = publishedData.slice();
                allPresentedData = presentedData.slice();

                const primary = '#1E3A8A';
                const gold = '#D4AF37';
                const success = '#059669';

                const submittedEl = document.getElementById('kmsarDeanSubmitted');
                if (submittedEl) {
                    submissionsChart = new Chart(submittedEl, {
                        type: 'line',
                        data: {
                            labels: yearLabels,
                            datasets: [{
                                label: @json(__('Research submitted')),
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
                    publishedChart = new Chart(publishedEl, {
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
                    presentedChart = new Chart(presentedEl, {
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

                const input = document.getElementById('yearRangeInput');
                if (input) {
                    updateCharts(input.value);
                }
            });
        </script>
    @endif
@endpush

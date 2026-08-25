@extends('layouts.app')

@push('scripts-head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
@endpush

@section('title', 'Admin Dashboard — ' . config('app.name', 'KMSAR'))

@section('navbar-context')
    Admin Dashboard
@endsection

@php
    $stageColors = ['#D97706', '#2563EB', '#059669', '#DC2626'];
@endphp

@section('content')
    <div class="kmsar-page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
        <div class="flex-1 min-w-0">
            <h1 class="kmsar-h1">Admin Dashboard</h1>
            <p class="kmsar-body mt-2">Overview of users, colleges, research activity and approval workload.</p>
        </div>
        <div class="kmsar-body" style="color:var(--color-text-muted);font-size:var(--text-sm);white-space:nowrap;">
            {{ now()->format('l, F j, Y') }}
        </div>
    </div>

    <form method="get" action="{{ route('admin.dashboard') }}" class="kmsar-card" style="margin-bottom:16px;padding:16px 20px;">
        <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <div>
                <label for="date_from" style="font-size:12px; font-weight:600; color:#64748B; display:block; margin-bottom:4px;">
                    {{ __('Research accepted from') }}
                </label>
                <input type="date"
                       id="date_from"
                       name="date_from"
                       value="{{ $dateFrom ?? '' }}"
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
                       value="{{ $dateTo ?? '' }}"
                       class="kmsar-input"
                       style="width:160px;">
            </div>
            <div>
                <button type="submit" class="kmsar-btn kmsar-btn--primary" style="padding:8px 20px;">
                    {{ __('Apply') }}
                </button>
                @if (! empty($dateFrom) || ! empty($dateTo))
                    <a href="{{ route('admin.dashboard') }}"
                       class="kmsar-btn kmsar-btn--outline"
                       style="padding:8px 20px; margin-left:8px;">
                        {{ __('Clear') }}
                    </a>
                @endif
            </div>
        </div>
    </form>

    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:20px;" role="region" aria-label="Dashboard statistics">
        <div class="kmsar-stat-card" style="position:relative;padding-top:2.75rem;">
            <div style="position:absolute;top:1rem;right:1rem;width:44px;height:44px;border-radius:50%;background:rgba(30,58,138,0.12);display:flex;align-items:center;justify-content:center;color:#1E3A8A;" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
            </div>
            <div class="kmsar-stat-card-value" style="color:#1E3A8A;">{{ number_format($totalUsers ?? 0) }}</div>
            <div class="kmsar-stat-card-label" style="margin-top:0.375rem;margin-bottom:0;">{{ __('Total users') }}</div>
        </div>
        <div class="kmsar-stat-card" style="position:relative;padding-top:2.75rem;">
            <div style="position:absolute;top:1rem;right:1rem;width:44px;height:44px;border-radius:50%;background:rgba(212,175,55,0.15);display:flex;align-items:center;justify-content:center;color:#D4AF37;" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H12v-.008zm0 3h.008v.008H12V9.75zm-3 3h.008v.008H9V9.75zm0 3h.008v.008H9V12.75zm-3 3h.008v.008H6V9.75zm0 3h.008v.008H6V12.75zm0 3h.008v.008H6V15.75zm0 3h.008v.008H6V18.75z" />
                </svg>
            </div>
            <div class="kmsar-stat-card-value" style="color:#D4AF37;">{{ number_format($totalColleges ?? 0) }}</div>
            <div class="kmsar-stat-card-label" style="margin-top:0.375rem;margin-bottom:0;">{{ __('Total colleges') }}</div>
        </div>
        <div class="kmsar-stat-card" style="position:relative;padding-top:2.75rem;">
            <div style="position:absolute;top:1rem;right:1rem;width:44px;height:44px;border-radius:50%;background:rgba(5,150,105,0.12);display:flex;align-items:center;justify-content:center;color:#059669;" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </div>
            <div class="kmsar-stat-card-value" style="color:#059669;">{{ number_format($totalResearch ?? 0) }}</div>
            <div class="kmsar-stat-card-label" style="margin-top:0.375rem;margin-bottom:0;">{{ __('Total research') }}</div>
        </div>
        <div class="kmsar-stat-card" data-stat-card="in-progress" style="position:relative;padding-top:2.75rem;">
            <div style="position:absolute;top:1rem;right:1rem;width:44px;height:44px;border-radius:50%;background:rgba(37,99,235,0.12);display:flex;align-items:center;justify-content:center;color:#2563EB;" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="kmsar-stat-card-value" style="color:#2563EB;">{{ number_format($researchInProgress ?? 0) }}</div>
            <div class="kmsar-stat-card-label" style="margin-top:0.375rem;margin-bottom:0;">{{ __('Research In Progress') }}</div>
            <div class="kmsar-stat-card-sub">{{ __('Proposal and ongoing') }}</div>
        </div>
        <div class="kmsar-stat-card" style="position:relative;padding-top:2.75rem;">
            <div style="position:absolute;top:1rem;right:1rem;width:44px;height:44px;border-radius:50%;background:rgba(217,119,6,0.12);display:flex;align-items:center;justify-content:center;color:#D97706;" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:22px;height:22px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="kmsar-stat-card-value" style="color:#D97706;">{{ number_format($pendingApprovals ?? 0) }}</div>
            <div class="kmsar-stat-card-label" style="margin-top:0.375rem;margin-bottom:0;">{{ __('Pending approvals') }}</div>
        </div>
    </div>

    @php
        $statusBreakdown = [
            'dean_review' => ['label' => __('Dean review'), 'color' => '#D97706'],
            'ovpri_review' => ['label' => __('OVPRI review'), 'color' => '#2563EB'],
            'approved' => ['label' => __('Approved'), 'color' => '#059669'],
            'rejected' => ['label' => __('Rejected'), 'color' => '#DC2626'],
        ];
    @endphp

    <div class="kmsar-card kmsar-card--accent-primary" style="margin-bottom:20px;">
        <div class="kmsar-card-header">
            <div>
                <h2 class="kmsar-card-title">{{ __('Research by approval stage') }}</h2>
                <p class="kmsar-body mt-1" style="font-size:0.875rem;color:var(--color-text-secondary);">{{ __('Submitted research only. Drafts stay with the faculty who created them.') }}</p>
            </div>
        </div>
        <div class="kmsar-card-body">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;" role="region" aria-label="{{ __('Research approval stage breakdown') }}">
                @foreach ($statusBreakdown as $key => $meta)
                    <div class="kmsar-stat-card" style="padding:1rem 1.125rem;">
                        <div class="kmsar-stat-card-value" style="color:{{ $meta['color'] }};font-size:1.5rem;">{{ number_format($researchByStatus[$key] ?? 0) }}</div>
                        <div class="kmsar-stat-card-label" style="margin-top:0.375rem;margin-bottom:0;">{{ $meta['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="kmsar-dashboard-chart-grid kmsar-dashboard-chart-grid--3-2">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Research by College/Office') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Research accepted per mother college') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap">
                    <canvas id="collegeChart" aria-label="{{ __('Research by College/Office chart') }}"></canvas>
                </div>
                @if (! empty($collegeBreakdown) && count($collegeBreakdown))
                    <div class="kmsar-chart-legend kmsar-chart-legend--horizontal">
                        @foreach ($collegeBreakdown as $row)
                            @continue(! filled($row['code'] ?? null) || ($row['code'] ?? '') === 'IS' || ($row['count'] ?? 0) < 1)
                            <div class="kmsar-legend-item">
                                <span class="kmsar-legend-dot kmsar-legend-dot--navy"></span>
                                <span>{{ $row['code'] }}: {{ number_format($row['count']) }} ({{ $row['percentage'] }}%)</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Research progress') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Accepted research only — one progress category per record (highest outcome)') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--compact">
                    <canvas id="progressChart" aria-label="{{ __('Research progress chart') }}"></canvas>
                </div>
                @if (($researchProgressBreakdown ?? collect())->sum('count') > 0)
                    <div class="kmsar-chart-legend">
                        @foreach ($researchProgressBreakdown as $idx => $row)
                            @if ($row['count'] > 0)
                                <div class="kmsar-legend-item">
                                    <span class="kmsar-legend-dot" style="background:{{ ['#1E3A8A', '#2563EB', '#059669', '#D97706', '#7C3AED', '#DC2626', '#0EA5E9', '#D4AF37', '#64748B', '#94A3B8'][$idx] ?? '#94A3B8' }};"></span>
                                    <span>{{ $row['label'] }}</span>
                                    <span class="kmsar-legend-value">{{ number_format($row['count']) }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if (! empty($collegeBreakdown) && count($collegeBreakdown))
        <div class="kmsar-chart-card" style="margin-bottom:20px;">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('College/Office breakdown') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Research accepted counts and share') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-table-wrap">
                    <table class="kmsar-table">
                        <thead>
                            <tr>
                                <th>{{ __('College/Office') }}</th>
                                <th>{{ __('Research') }}</th>
                                <th>{{ __('%') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($collegeBreakdown as $row)
                                @continue(! filled($row['code'] ?? null) || ($row['code'] ?? '') === 'IS')
                                <tr>
                                    <td class="kmsar-table-cell-title">{{ $row['code'] }}</td>
                                    <td>{{ number_format($row['count']) }}</td>
                                    <td>{{ $row['percentage'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="kmsar-chart-card" style="margin-bottom:20px;">
        <div class="kmsar-chart-header">
            <div>
                <h2 class="kmsar-chart-title">{{ __('SDG Distribution') }}</h2>
                <p class="kmsar-chart-subtitle">{{ __('Research accepted only, aligned to each Sustainable Development Goal') }}</p>
            </div>
        </div>
        <div class="kmsar-chart-body">
            <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--sdg">
                <canvas id="adminSdgChart" aria-label="{{ __('SDG Distribution') }}"></canvas>
            </div>
        </div>
    </div>

    <div class="kmsar-dashboard-chart-grid kmsar-dashboard-chart-grid--3-2">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Acceptance trend') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Research accepted by month') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap">
                    <canvas id="monthlyChart" aria-label="{{ __('Monthly submission trend chart') }}"></canvas>
                </div>
                <div class="kmsar-chart-summary">
                    <div class="kmsar-chart-summary-item">
                        <span class="kmsar-chart-summary-value">{{ number_format($submissionsThisYear ?? 0) }}</span>
                        <span class="kmsar-chart-summary-label">{{ __('Total accepted this year') }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Research classification') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Funding and type breakdown') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--compact">
                    <canvas id="classChart" aria-label="{{ __('Research classification chart') }}"></canvas>
                </div>
                @if (! empty($researchByClassification['labels']))
                    <div class="kmsar-chart-legend">
                        @foreach ($researchByClassification['labels'] as $i => $label)
                            <div class="kmsar-legend-item">
                                <span class="kmsar-legend-dot" style="background:{{ $researchByClassification['colors'][$i] ?? '#94A3B8' }};"></span>
                                <span>{{ $label }}</span>
                                <span class="kmsar-legend-value">{{ number_format($researchByClassification['counts'][$i] ?? 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="kmsar-chart-card" style="margin-bottom:20px;">
        <div class="kmsar-chart-header">
            <div>
                <h2 class="kmsar-chart-title">{{ __('AUF Research Agenda theme alignment') }}</h2>
                <p class="kmsar-chart-subtitle">{{ __('Accepted research aligned to institutional agenda themes') }}</p>
            </div>
        </div>
        <div class="kmsar-chart-body">
            <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--compact" style="max-width:520px;margin:0 auto;">
                <canvas id="adminAgendaThemeChart" aria-label="{{ __('Agenda theme alignment') }}"></canvas>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const collegeBreakdown = @json(collect($collegeBreakdown ?? [])).filter(function (r) {
        const code = (r.code || r.label || '').toString().trim();
        return code !== '' && code !== 'IS';
    });
    const researchByCollege = collegeBreakdown.length
        ? collegeBreakdown.map(function (r) { return { label: r.code, count: r.count, percentage: r.percentage }; })
        : @json(collect($researchByCollege ?? [])).filter(function (r) {
            const code = (r.label || r.code || '').toString().trim();
            return code !== '' && code !== 'IS';
        });
    const collegeChartRows = researchByCollege.filter(function (r) { return (r.count || 0) > 0; });
    const researchProgressBreakdown = @json($researchProgressBreakdown ?? collect());
    const progressChartLabels = researchProgressBreakdown.map(function (r) { return r.label; });
    const progressChartCounts = researchProgressBreakdown.map(function (r) { return r.count; });
    const progressChartColors = ['#1E3A8A', '#2563EB', '#059669', '#D97706', '#7C3AED', '#DC2626', '#0EA5E9', '#D4AF37', '#64748B', '#94A3B8'];
    const monthlySubmissions = @json($monthlySubmissions ?? ['labels' => [], 'counts' => []]);
    const researchByClassification = @json($researchByClassification ?? ['labels' => [], 'counts' => [], 'colors' => []]);
    const agendaThemeLabels = @json(collect($agendaThemeBreakdown ?? [])->pluck('label'));
    const agendaThemeCounts = @json(collect($agendaThemeBreakdown ?? [])->pluck('count'));
    const agendaThemeColors = ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#D97706', '#7C3AED'];
    const sdgData = @json(array_values($sdgCounts ?? array_fill(1, 17, 0)));
    const collegePercentages = collegeChartRows.map(function (r) {
        return r.percentage !== undefined ? r.percentage : 0;
    });

    const collegeLabels = collegeChartRows.map(function (r) { return r.label || r.code; });
    const collegeCounts = collegeChartRows.map(function (r) { return r.count; });

    if (typeof Chart === 'undefined') {
        return;
    }

    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#94A3B8';
    Chart.defaults.plugins.legend.display = false;
    Chart.defaults.plugins.tooltip.backgroundColor = '#0F172A';
    Chart.defaults.plugins.tooltip.titleColor = '#fff';
    Chart.defaults.plugins.tooltip.bodyColor = '#CBD5E1';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;

    const scaleCommon = {
        x: {
            grid: { display: false },
            border: { display: false },
        },
        y: {
            beginAtZero: true,
            grid: { color: '#E2E8F0' },
            border: { display: false },
        },
    };

    const collegeCtx = document.getElementById('collegeChart');
    if (collegeCtx) {
        new Chart(collegeCtx, {
            type: 'bar',
            data: {
                labels: collegeLabels,
                datasets: [{
                    label: 'Research',
                    data: collegeCounts,
                    backgroundColor: '#1E3A8A',
                    hoverBackgroundColor: '#2563EB',
                    borderRadius: 6,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 4 },
                scales: {
                    ...scaleCommon,
                    y: {
                        beginAtZero: true,
                        grace: '5%',
                        ticks: { precision: 0, stepSize: 1 },
                        grid: { color: '#E2E8F0' },
                        border: { display: false },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const pct = collegePercentages[context.dataIndex] ?? 0;
                                return context.dataset.label + ': ' + context.parsed.y + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            },
        });
    }

    const sdgLabels = [
        'SDG 1: No Poverty', 'SDG 2: Zero Hunger', 'SDG 3: Good Health',
        'SDG 4: Quality Education', 'SDG 5: Gender Equality', 'SDG 6: Clean Water',
        'SDG 7: Clean Energy', 'SDG 8: Decent Work', 'SDG 9: Industry & Innovation',
        'SDG 10: Reduced Inequalities', 'SDG 11: Sustainable Cities',
        'SDG 12: Responsible Consumption', 'SDG 13: Climate Action',
        'SDG 14: Life Below Water', 'SDG 15: Life on Land',
        'SDG 16: Peace & Justice', 'SDG 17: Partnerships'
    ];
    const sdgCtx = document.getElementById('adminSdgChart');
    if (sdgCtx) {
        new Chart(sdgCtx, {
            type: 'bar',
            data: {
                labels: sdgLabels,
                datasets: [{
                    label: 'Research Count',
                    data: sdgData,
                    backgroundColor: '#1E3A8A',
                    hoverBackgroundColor: '#D4AF37',
                    borderRadius: 4,
                    borderSkipped: false,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const total = sdgData.reduce(function (a, b) { return a + b; }, 0);
                                const pct = total > 0 ? Math.round(context.parsed.x / total * 100) : 0;
                                return context.parsed.x + ' research (' + pct + '%)';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: '#E2E8F0' },
                        border: { display: false },
                    },
                    y: {
                        ticks: { autoSkip: false, font: { size: 10 } },
                        grid: { display: false },
                        border: { display: false },
                    },
                },
            },
        });
    }

    const progressCtx = document.getElementById('progressChart');
    if (progressCtx && progressChartCounts.length && progressChartCounts.some(function (n) { return n > 0; })) {
        new Chart(progressCtx, {
            type: 'pie',
            data: {
                labels: progressChartLabels,
                datasets: [{
                    data: progressChartCounts,
                    backgroundColor: progressChartLabels.map(function (_, i) { return progressChartColors[i] ?? '#94A3B8'; }),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 8 },
            },
        });
    }

    const monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlySubmissions.labels,
                datasets: [{
                    label: 'Submissions',
                    data: monthlySubmissions.counts,
                    borderColor: '#1E3A8A',
                    backgroundColor: 'rgba(30,58,138,0.06)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#1E3A8A',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: scaleCommon,
            },
        });
    }

    const classCtx = document.getElementById('classChart');
    if (classCtx && researchByClassification.labels && researchByClassification.labels.length) {
        new Chart(classCtx, {
            type: 'doughnut',
            data: {
                labels: researchByClassification.labels,
                datasets: [{
                    data: researchByClassification.counts,
                    backgroundColor: researchByClassification.colors && researchByClassification.colors.length
                        ? researchByClassification.colors
                        : ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#94A3B8'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 8 },
                cutout: '62%',
            },
        });
    }

    const agendaCtx = document.getElementById('adminAgendaThemeChart');
    if (agendaCtx && agendaThemeCounts.some(function (n) { return n > 0; })) {
        new Chart(agendaCtx, {
            type: 'pie',
            data: {
                labels: agendaThemeLabels,
                datasets: [{
                    data: agendaThemeCounts,
                    backgroundColor: agendaThemeLabels.map(function (_, i) { return agendaThemeColors[i] ?? '#94A3B8'; }),
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

<script>
    // Auto-refresh page every 2 minutes to keep counts current
    setTimeout(function () {
        window.location.reload();
    }, 120000);
</script>
@endpush

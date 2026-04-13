@extends('layouts.app')

@push('scripts-head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
@endpush

@section('title', 'Admin Dashboard — ' . config('app.name', 'KMSAR'))

@section('navbar-context')
    Admin Dashboard
@endsection

@php
    $stageColors = ['#94A3B8', '#D97706', '#2563EB', '#059669', '#DC2626'];
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

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px;" role="region" aria-label="Dashboard statistics">
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

    <div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:20px;">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Research by college') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Count of registered research per mother college') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body" style="padding:20px;">
                <div style="position:relative; height:280px; width:100%;">
                    <canvas id="collegeChart" aria-label="{{ __('Research by college chart') }}"></canvas>
                </div>
                @if (! empty($researchByCollege))
                    <div class="kmsar-chart-legend kmsar-chart-legend--horizontal">
                        @foreach ($researchByCollege as $row)
                            <div class="kmsar-legend-item">
                                <span class="kmsar-legend-dot" style="background:#1E3A8A;"></span>
                                <span>{{ $row['label'] }}</span>
                                <span class="kmsar-legend-value">{{ number_format($row['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Approval stage breakdown') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Distribution by workflow stage') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body" style="padding:20px;">
                <div style="position:relative; height:280px; width:100%;">
                    <canvas id="stageChart" aria-label="{{ __('Approval stage chart') }}"></canvas>
                </div>
                @if (! empty($researchByStage['labels']))
                    <div class="kmsar-chart-legend">
                        @foreach ($researchByStage['labels'] as $i => $label)
                            <div class="kmsar-legend-item">
                                <span class="kmsar-legend-dot" style="background:{{ $stageColors[$i] ?? '#94A3B8' }};"></span>
                                <span>{{ $label }}</span>
                                <span class="kmsar-legend-value">{{ number_format($researchByStage['counts'][$i] ?? 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:20px;">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Submission trend') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('New research registrations by month') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body" style="padding:20px;">
                <div style="position:relative; height:280px; width:100%;">
                    <canvas id="monthlyChart" aria-label="{{ __('Monthly submission trend chart') }}"></canvas>
                </div>
                <div class="kmsar-chart-summary">
                    <div class="kmsar-chart-summary-item">
                        <span class="kmsar-chart-summary-value">{{ number_format($submissionsThisYear ?? 0) }}</span>
                        <span class="kmsar-chart-summary-label">{{ __('Total submissions this year') }}</span>
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
            <div class="kmsar-chart-body" style="padding:20px;">
                <div style="position:relative; height:280px; width:100%;">
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const researchByCollege = @json(collect($researchByCollege ?? []));
    const researchByStage = @json($researchByStage ?? ['labels' => [], 'counts' => []]);
    const monthlySubmissions = @json($monthlySubmissions ?? ['labels' => [], 'counts' => []]);
    const researchByClassification = @json($researchByClassification ?? ['labels' => [], 'counts' => [], 'colors' => []]);

    const collegeLabels = researchByCollege.map(function (r) { return r.label; });
    const collegeCounts = researchByCollege.map(function (r) { return r.count; });

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
                scales: scaleCommon,
            },
        });
    }

    const stageColors = ['#94A3B8', '#D97706', '#2563EB', '#059669', '#DC2626'];
    const stageCtx = document.getElementById('stageChart');
    if (stageCtx && researchByStage.counts && researchByStage.counts.length) {
        new Chart(stageCtx, {
            type: 'doughnut',
            data: {
                labels: researchByStage.labels,
                datasets: [{
                    data: researchByStage.counts,
                    backgroundColor: stageColors,
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
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
                cutout: '65%',
            },
        });
    }
});
</script>
@endpush

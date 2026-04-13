@extends('layouts.app')

@push('scripts-head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
@endpush

@section('title', __('University dashboard — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('OVPRI') }}
@endsection

@php
    $ovpriClassLegendColors = ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#94A3B8'];
@endphp

@section('content')
    <x-page-header
        :title="__('University dashboard')"
        :subtitle="__('Research volume, approvals, and publication distribution across colleges')"
        :breadcrumb="[
            ['label' => __('Dashboard')],
        ]"
    />

    {{-- Section 2 — Stat cards --}}
    <div class="kmsar-stats-grid kmsar-animate-in mb-8" role="region" aria-label="{{ __('Dashboard statistics') }}">
        <div class="kmsar-stat-card kmsar-card--accent-primary">
            <div class="kmsar-stat-card-label">{{ __('Total research') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($totalResearch) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-pending">
            <div class="kmsar-stat-card-label">{{ __('Pending approval') }}</div>
            <div class="kmsar-stat-card-value kmsar-stat-card-value--pending">{{ number_format($pendingApprovals) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-success">
            <div class="kmsar-stat-card-label">{{ __('Published') }}</div>
            <div class="kmsar-stat-card-value kmsar-stat-card-value--approved">{{ number_format($publishedCount) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-gold">
            <div class="kmsar-stat-card-label">{{ __('Scopus / indexed') }}</div>
            <div class="kmsar-stat-card-value" style="color: var(--color-gold);">{{ number_format($scopusCount) }}</div>
        </div>
    </div>

    {{-- Section 3 — Research by college + SDG distribution --}}
    <div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:16px;">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Research by college') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Total registered research by mother college') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div style="position:relative;height:280px;width:100%;">
                    <canvas id="kmsarOvpriByCollege" aria-label="{{ __('Research by college') }}"></canvas>
                </div>
            </div>
        </div>
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('SDG Distribution') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Most aligned Sustainable Development Goals') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body" style="padding:20px;">
                <div style="position:relative;height:280px;width:100%;">
                    <canvas id="sdgChart" aria-label="{{ __('SDG Distribution') }}"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Section 4 — Scopus, Presented, Classification --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Scopus indexed per college') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Records flagged as Scopus indexed') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div style="position:relative;height:200px;width:100%;">
                    <canvas id="kmsarOvpriScopus" aria-label="{{ __('Scopus indexed per college') }}"></canvas>
                </div>
            </div>
        </div>
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Presented per college') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Internal and external presentations') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div style="position:relative;height:200px;width:100%;">
                    <canvas id="kmsarOvpriPresented" aria-label="{{ __('Presented research per college') }}"></canvas>
                </div>
            </div>
        </div>
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('By classification') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Funding and type breakdown') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body" style="padding:12px;">
                <div style="position:relative;height:200px;width:100%;">
                    <canvas id="kmsarOvpriClassification" aria-label="{{ __('Research classification') }}"></canvas>
                </div>
                @if ($classificationBreakdown->count())
                    <div class="kmsar-chart-legend" style="margin-top:8px;font-size:0.75rem;line-height:1.35;">
                        @foreach ($classificationBreakdown as $idx => $row)
                            <div class="kmsar-legend-item">
                                <span class="kmsar-legend-dot" style="background:{{ $ovpriClassLegendColors[$idx] ?? '#94A3B8' }};"></span>
                                <span>{{ $row['label'] }}</span>
                                <span class="kmsar-legend-value">{{ number_format($row['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Section 5 — Monthly submission trend (full width) --}}
    <div class="kmsar-chart-card">
        <div class="kmsar-chart-header">
            <div>
                <h2 class="kmsar-chart-title">{{ __('Submission trend') }}</h2>
                <p class="kmsar-chart-subtitle">{{ __('New research registrations by month (last 12 months)') }}</p>
            </div>
        </div>
        <div class="kmsar-chart-body">
            <div style="position:relative;height:280px;width:100%;">
                <canvas id="kmsarOvpriMonthlyTrend" aria-label="{{ __('Monthly submission trend') }}"></canvas>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            const collegeLabels = @json($researchByCollege->pluck('label'));
            const collegeData = @json($researchByCollege->pluck('count'));
            const scopusData = @json($scopusByCollege->pluck('count'));
            const presentedData = @json($presentedByCollege->pluck('count'));
            const classificationLabels = @json($classificationBreakdown->pluck('label'));
            const classificationCounts = @json($classificationBreakdown->pluck('count'));
            const sdgLabels = @json($sdgDistribution->pluck('label')->values());
            const sdgCounts = @json($sdgDistribution->pluck('count')->values());
            const sdgNums = @json($sdgDistribution->pluck('sdg')->values());
            const monthlyLabels = @json($monthlyTrend->pluck('label'));
            const monthlyCounts = @json($monthlyTrend->pluck('count'));

            const primary = '#1E3A8A';
            const gold = '#D4AF37';
            const success = '#059669';
            const sdgColors = [
                '#E5243B', '#DDA63A', '#4C9F38', '#C5192D', '#FF3A21',
                '#26BDE2', '#FCC30B', '#A21942', '#FD6925', '#DD1367',
                '#FD9D24', '#BF8B2E', '#3F7E44', '#0A97D9', '#56C02B',
                '#00689D', '#19486A',
            ];
            const classColors = ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#94A3B8'];

            const barOptionsShort = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { ticks: { maxRotation: 45, minRotation: 0 } },
                },
            };

            const byCollegeEl = document.getElementById('kmsarOvpriByCollege');
            if (byCollegeEl) {
                new Chart(byCollegeEl, {
                    type: 'bar',
                    data: {
                        labels: collegeLabels,
                        datasets: [{
                            label: @json(__('Research')),
                            data: collegeData,
                            backgroundColor: primary,
                            borderColor: primary,
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
                            x: { stacked: false, ticks: { maxRotation: 45, minRotation: 0 } },
                            y: { beginAtZero: true, ticks: { precision: 0 } },
                        },
                    },
                });
            }

            const sdgEl = document.getElementById('sdgChart');
            if (sdgEl && sdgLabels.length) {
                const sdgBgColors = sdgNums.map((n) => sdgColors[n - 1] ?? '#94A3B8');
                new Chart(sdgEl, {
                    type: 'doughnut',
                    data: {
                        labels: sdgLabels,
                        datasets: [{
                            data: sdgCounts,
                            backgroundColor: sdgBgColors,
                            borderWidth: 2,
                            borderColor: '#fff',
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '60%',
                        plugins: {
                            legend: {
                                display: true,
                                position: 'right',
                                labels: { font: { size: 10 }, padding: 8, boxWidth: 12 },
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => ctx.label + ': ' + ctx.raw + ' research(es)',
                                },
                            },
                        },
                    },
                });
            }

            const scopusEl = document.getElementById('kmsarOvpriScopus');
            if (scopusEl) {
                new Chart(scopusEl, {
                    type: 'bar',
                    data: {
                        labels: collegeLabels,
                        datasets: [{
                            label: @json(__('Scopus indexed')),
                            data: scopusData,
                            backgroundColor: gold,
                            borderColor: gold,
                            borderWidth: 1,
                            borderRadius: 4,
                        }],
                    },
                    options: barOptionsShort,
                });
            }

            const presentedEl = document.getElementById('kmsarOvpriPresented');
            if (presentedEl) {
                new Chart(presentedEl, {
                    type: 'bar',
                    data: {
                        labels: collegeLabels,
                        datasets: [{
                            label: @json(__('Presented')),
                            data: presentedData,
                            backgroundColor: success,
                            borderColor: success,
                            borderWidth: 1,
                            borderRadius: 4,
                        }],
                    },
                    options: barOptionsShort,
                });
            }

            const classEl = document.getElementById('kmsarOvpriClassification');
            if (classEl && classificationLabels.length) {
                new Chart(classEl, {
                    type: 'doughnut',
                    data: {
                        labels: classificationLabels,
                        datasets: [{
                            data: classificationCounts,
                            backgroundColor: classColors,
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: { display: false },
                        },
                    },
                });
            }

            const monthlyEl = document.getElementById('kmsarOvpriMonthlyTrend');
            if (monthlyEl) {
                new Chart(monthlyEl, {
                    type: 'line',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: @json(__('Submissions')),
                            data: monthlyCounts,
                            borderColor: primary,
                            backgroundColor: 'rgba(30, 58, 138, 0.08)',
                            fill: true,
                            tension: 0.25,
                            borderWidth: 2,
                            pointBackgroundColor: primary,
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
        });
    </script>
@endpush

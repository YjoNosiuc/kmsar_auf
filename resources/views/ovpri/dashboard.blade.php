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

    <form method="get" action="{{ route('ovpri.dashboard') }}" class="kmsar-card" style="margin-bottom:16px;padding:16px 20px;">
        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;">
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
                    <a href="{{ route('ovpri.dashboard') }}"
                       class="kmsar-btn kmsar-btn--outline"
                       style="padding:8px 20px; margin-left:8px;">
                        {{ __('Clear') }}
                    </a>
                @endif
            </div>
            <div style="flex:1;min-width:220px;">
                <label for="collegeSearch" style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;">{{ __('Search college') }}</label>
                <input
                    id="collegeSearch"
                    type="search"
                    name="college"
                    class="kmsar-input"
                    style="width:100%;"
                    value="{{ $collegeTerm ?? ($selectedCollege->code ?? '') }}"
                    placeholder="{{ __('Filter charts by college code or name…') }}"
                    autocomplete="off"
                >
                <p class="kmsar-chart-subtitle" style="margin-top:6px;">{{ __('Type a college code (for example CCS) and click Apply to open the program/dept breakdown.') }}</p>
            </div>
        </div>
    </form>

    {{-- Section 2 — Stat cards --}}
    <div class="kmsar-stats-grid kmsar-stats-grid--5 kmsar-animate-in mb-8" role="region" aria-label="{{ __('Dashboard statistics') }}">
        <div class="kmsar-stat-card kmsar-card--accent-primary">
            <div class="kmsar-stat-card-label">{{ __('Total research') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($totalResearch) }}</div>
            <div class="kmsar-stat-card-sub">{{ __('Completed records only') }}</div>
        </div>
        <div class="kmsar-stat-card" data-stat-card="in-progress">
            <div class="kmsar-stat-card-label">{{ __('Research In Progress') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($researchInProgress ?? 0) }}</div>
            <div class="kmsar-stat-card-sub">{{ __('Proposal and ongoing') }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-pending">
            <div class="kmsar-stat-card-label">{{ __('Pending OVPRI approval') }}</div>
            <div class="kmsar-stat-card-value kmsar-stat-card-value--pending">{{ number_format($pendingApprovals) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-gold">
            <div class="kmsar-stat-card-label">{{ __('Scopus/WoS Indexed') }}</div>
            <div class="kmsar-stat-card-value" style="color: var(--color-gold);">{{ number_format($scopusCount) }}</div>
        </div>
        <div class="kmsar-stat-card" data-stat-card="engaged">
            <div class="kmsar-stat-card-label">{{ __('Faculty/Staff Engaged') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($engagedTotal ?? 0) }}</div>
            <div class="kmsar-stat-card-sub">{{ __('Unique researchers in KMSAR') }}</div>
        </div>
    </div>

    {{-- Section 3 — Research by college + SDG distribution --}}
    <div class="kmsar-dashboard-chart-grid kmsar-dashboard-chart-grid--3-2">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Research by College/Office') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Research accepted by mother college') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap">
                    <canvas id="kmsarOvpriByCollege" aria-label="{{ __('Research by College/Office') }}"></canvas>
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
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--compact">
                    <canvas id="sdgChart" aria-label="{{ __('SDG Distribution') }}"></canvas>
                </div>
            </div>
        </div>
    </div>

    @php $collegeBreakdownRows = $collegeBreakdown ?? $researchByCollege; @endphp
    @if (count($collegeBreakdownRows))
        <div class="kmsar-chart-card" style="margin-bottom:16px;">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('College/Office breakdown') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Research accepted counts and share') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-table-wrap" id="collegeBreakdownTable">
                    <table class="kmsar-table">
                        <thead>
                            <tr>
                                <th>{{ __('College/Office') }}</th>
                                <th>{{ __('Research') }}</th>
                                <th>{{ __('%') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($collegeBreakdownRows as $item)
                                <tr data-college-code="{{ $item['code'] ?? $item['label'] }}">
                                    <td class="kmsar-table-cell-title">{{ $item['code'] ?? $item['label'] }}</td>
                                    <td>{{ number_format($item['count']) }}</td>
                                    <td>{{ $item['percentage'] ?? 0 }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if (! empty($selectedCollege))
        <div class="kmsar-chart-card" style="margin-bottom:16px;">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Program/Dept Breakdown') }} — {{ $selectedCollege->code }}</h2>
                    <p class="kmsar-chart-subtitle">{{ $selectedCollege->name }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                @if (count($programBreakdown) > 0)
                    <div class="kmsar-table-wrap">
                        <table class="kmsar-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Program/Dept') }}</th>
                                    <th>{{ __('Research Count') }}</th>
                                    <th>{{ __('%') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalProg = collect($programBreakdown)->sum('count'); @endphp
                                @foreach ($programBreakdown as $prog)
                                    <tr>
                                        <td class="kmsar-table-cell-title">{{ $prog['code'] }}</td>
                                        <td>{{ number_format($prog['count']) }}</td>
                                        <td>{{ $totalProg > 0 ? round($prog['count'] / $totalProg * 100, 1) : 0 }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="kmsar-body">{{ __('No program or department counts are available for this college yet. Counts use the primary author’s program assignment.') }}</p>
                @endif
            </div>
        </div>
    @endif

    <div class="kmsar-chart-card" style="margin-bottom:16px;">
        <div class="kmsar-chart-header">
            <div>
                <h2 class="kmsar-chart-title">{{ __('Acceptance trend — last 3 years') }}</h2>
                <p class="kmsar-chart-subtitle">{{ __('Monthly research accepted by OVPRI') }}</p>
            </div>
        </div>
        <div class="kmsar-chart-body">
            <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--tall">
                <canvas id="submissionTrendChart" aria-label="{{ __('Submission trend last 3 years') }}"></canvas>
            </div>
        </div>
    </div>

    <div class="kmsar-chart-card" style="margin-bottom:16px;">
        <div class="kmsar-chart-header">
            <div>
                <h2 class="kmsar-chart-title">{{ __('Faculty/Staff Engaged by College/Office') }}</h2>
                <p class="kmsar-chart-subtitle">{{ __('Unique faculty and staff listed as authors') }}</p>
            </div>
        </div>
        <div class="kmsar-chart-body">
            <div class="kmsar-chart-canvas-wrap">
                <canvas id="engagedByCollegeChart" aria-label="{{ __('Faculty/Staff Engaged by College/Office') }}"></canvas>
            </div>
        </div>
    </div>

    {{-- Section 4 — Scopus/WoS, Presented, Classification --}}
    <div class="kmsar-dashboard-chart-grid kmsar-dashboard-chart-grid--3">
        <div class="kmsar-chart-card">
            <div class="kmsar-chart-header">
                <div>
                    <h2 class="kmsar-chart-title">{{ __('Scopus/WoS Indexed by College/Office') }}</h2>
                    <p class="kmsar-chart-subtitle">{{ __('Records flagged as Scopus/WoS Indexed') }}</p>
                </div>
            </div>
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--short">
                    <canvas id="kmsarOvpriScopus" aria-label="{{ __('Scopus/WoS Indexed by College/Office') }}"></canvas>
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
                <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--short">
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
            <div class="kmsar-chart-body">
                <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--short">
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
                <canvas id="kmsarOvpriAgendaThemes" aria-label="{{ __('Agenda theme alignment') }}"></canvas>
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

    {{-- Workflow / approval status --}}
    <div class="kmsar-chart-card" style="margin-bottom:16px;">
        <div class="kmsar-chart-header">
            <div>
                    <h2 class="kmsar-chart-title">{{ __('Research by Approval Stage') }}</h2>
                <p class="kmsar-chart-subtitle">{{ __('Workflow status across dean review, OVPRI review, approved, and rejected') }}</p>
            </div>
        </div>
        <div class="kmsar-chart-body">
            <div class="kmsar-chart-canvas-wrap kmsar-chart-canvas-wrap--short">
                <canvas id="kmsarOvpriWorkflow" aria-label="{{ __('Research by Approval Stage') }}"></canvas>
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

            const allCollegeRows = @json($researchByCollege->values()).filter(function (row) {
                return (row.count || 0) > 0;
            });
            const allScopusRows = @json($scopusByCollege->values()).filter(function (row) {
                return (row.count || 0) > 0;
            });
            const allPresentedRows = @json($presentedByCollege->values()).filter(function (row) {
                return (row.count || 0) > 0;
            });
            const classificationLabels = @json($classificationBreakdown->pluck('label'));
            const classificationCounts = @json($classificationBreakdown->pluck('count'));
            const sdgLabels = @json($sdgDistribution->pluck('label')->values());
            const sdgCounts = @json($sdgDistribution->pluck('count')->values());
            const sdgNums = @json($sdgDistribution->pluck('sdg')->values());
            const workflowLabels = @json($workflowStatus->pluck('label'));
            const workflowCounts = @json($workflowStatus->pluck('count'));
            const submissionTrend = @json($submissionTrend ?? []);
            const engagedByCollege = @json(($engagedByCollege ?? collect())->values());
            const agendaThemeLabels = @json(collect($agendaThemeBreakdown ?? [])->pluck('label'));
            const agendaThemeCounts = @json(collect($agendaThemeBreakdown ?? [])->pluck('count'));
            const agendaThemeColors = ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#D97706', '#7C3AED'];

            const primary = '#1E3A8A';
            const gold = '#D4AF37';
            const success = '#059669';
            const warning = '#D97706';
            const danger = '#DC2626';
            const sdgColors = [
                '#E5243B', '#DDA63A', '#4C9F38', '#C5192D', '#FF3A21',
                '#26BDE2', '#FCC30B', '#A21942', '#FD6925', '#DD1367',
                '#FD9D24', '#BF8B2E', '#3F7E44', '#0A97D9', '#56C02B',
                '#00689D', '#19486A',
            ];
            const classColors = ['#1E3A8A', '#D4AF37', '#059669', '#2563EB', '#94A3B8'];
            const workflowColors = [warning, '#2563EB', success, danger];

            let byCollegeChart;
            let scopusChart;
            let presentedChart;

            function filterCollegeRows(rows, term) {
                const q = (term || '').trim().toLowerCase();
                if (!q) {
                    return rows;
                }
                return rows.filter((row) => {
                    return (row.label || '').toLowerCase().includes(q)
                        || (row.name || '').toLowerCase().includes(q);
                });
            }

            function applyCollegeFilter(term) {
                const collegeRows = filterCollegeRows(allCollegeRows, term);
                const labels = collegeRows.map((row) => row.label);
                const researchData = collegeRows.map((row) => row.count);
                const scopusData = filterCollegeRows(allScopusRows, term).map((row) => row.count);
                const presentedData = filterCollegeRows(allPresentedRows, term).map((row) => row.count);

                if (byCollegeChart) {
                    byCollegeChart.data.labels = labels;
                    byCollegeChart.data.datasets[0].data = researchData;
                    byCollegeChart.update();
                }
                if (scopusChart) {
                    scopusChart.data.labels = labels;
                    scopusChart.data.datasets[0].data = scopusData;
                    scopusChart.update();
                }
                if (presentedChart) {
                    presentedChart.data.labels = labels;
                    presentedChart.data.datasets[0].data = presentedData;
                    presentedChart.update();
                }

                const visibleCodes = labels.map((code) => String(code).toLowerCase());
                document.querySelectorAll('#collegeBreakdownTable tbody tr[data-college-code]').forEach(function (tr) {
                    const code = (tr.getAttribute('data-college-code') || '').toLowerCase();
                    const show = !term || !String(term).trim() || visibleCodes.indexOf(code) !== -1;
                    tr.hidden = !show;
                });
            }

            const barOptionsShort = {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 4 },
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true, grace: '5%', ticks: { precision: 0, stepSize: 1 } },
                    x: { ticks: { maxRotation: 45, minRotation: 0 } },
                },
            };

            const byCollegeEl = document.getElementById('kmsarOvpriByCollege');
            if (byCollegeEl) {
                byCollegeChart = new Chart(byCollegeEl, {
                    type: 'bar',
                    data: {
                        labels: allCollegeRows.map((row) => row.label),
                        datasets: [{
                            label: @json(__('Research')),
                            data: allCollegeRows.map((row) => row.count),
                            backgroundColor: primary,
                            borderColor: primary,
                            borderWidth: 1,
                            borderRadius: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: 4 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const data = context.dataset.data || [];
                                        const total = data.reduce(function (a, b) { return a + b; }, 0);
                                        const val = context.parsed.y;
                                        const pct = total > 0 ? (val / total * 100).toFixed(1) : '0.0';
                                        return context.dataset.label + ': ' + val + ' (' + pct + '%)';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { stacked: false, ticks: { maxRotation: 45, minRotation: 0 } },
                            y: { beginAtZero: true, grace: '5%', ticks: { precision: 0, stepSize: 1 } },
                        },
                    },
                });
            }

            const workflowEl = document.getElementById('kmsarOvpriWorkflow');
            if (workflowEl) {
                new Chart(workflowEl, {
                    type: 'bar',
                    data: {
                        labels: workflowLabels,
                        datasets: [{
                            label: @json(__('Research count')),
                            data: workflowCounts,
                            backgroundColor: workflowColors,
                            borderColor: workflowColors,
                            borderWidth: 1,
                            borderRadius: 4,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0 } },
                            y: { ticks: { autoSkip: false } },
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
                        rotation: -Math.PI / 2,
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
                scopusChart = new Chart(scopusEl, {
                    type: 'bar',
                    data: {
                        labels: allScopusRows.map((row) => row.label),
                        datasets: [{
                            label: @json(__('Scopus/WoS Indexed')),
                            data: allScopusRows.map((row) => row.count),
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
                presentedChart = new Chart(presentedEl, {
                    type: 'bar',
                    data: {
                        labels: allPresentedRows.map((row) => row.label),
                        datasets: [{
                            label: @json(__('Presented')),
                            data: allPresentedRows.map((row) => row.count),
                            backgroundColor: success,
                            borderColor: success,
                            borderWidth: 1,
                            borderRadius: 4,
                        }],
                    },
                    options: barOptionsShort,
                });
            }

            const collegeSearchEl = document.getElementById('collegeSearch');
            if (collegeSearchEl) {
                collegeSearchEl.addEventListener('input', function () {
                    applyCollegeFilter(this.value);
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
                        layout: { padding: 8 },
                        cutout: '62%',
                        plugins: {
                            legend: { display: false },
                        },
                    },
                });
            }

            const agendaEl = document.getElementById('kmsarOvpriAgendaThemes');
            if (agendaEl && agendaThemeCounts.some(function (n) { return n > 0; })) {
                new Chart(agendaEl, {
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

            const submissionTrendEl = document.getElementById('submissionTrendChart');
            if (submissionTrendEl && submissionTrend.length) {
                new Chart(submissionTrendEl, {
                    type: 'line',
                    data: {
                        labels: submissionTrend.map(function (row) { return row.label; }),
                        datasets: [{
                            label: 'Research Accepted',
                            data: submissionTrend.map(function (row) { return row.count; }),
                            borderColor: '#1E3A8A',
                            backgroundColor: 'rgba(30,58,138,0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#D4AF37',
                            pointRadius: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } },
                        },
                    },
                });
            }

            const engagedEl = document.getElementById('engagedByCollegeChart');
            if (engagedEl) {
                new Chart(engagedEl, {
                    type: 'bar',
                    data: {
                        labels: engagedByCollege.map(function (row) { return row.code; }),
                        datasets: [{
                            label: 'Faculty/Staff Engaged',
                            data: engagedByCollege.map(function (row) { return row.count; }),
                            backgroundColor: '#D4AF37',
                            borderColor: '#D4AF37',
                            borderRadius: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                    },
                });
            }

            if (collegeSearchEl && collegeSearchEl.value) {
                applyCollegeFilter(collegeSearchEl.value);
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

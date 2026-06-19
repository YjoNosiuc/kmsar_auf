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
        <form method="get" action="{{ route('dean.dashboard') }}" class="kmsar-card" style="margin-bottom:16px;padding:16px 20px;">
            <div style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px;">
                <div style="min-width:200px;">
                    <label for="academic_year" style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;">{{ __('Academic year') }}</label>
                    <select id="academic_year" name="academic_year" class="kmsar-select" style="width:100%;" onchange="this.form.submit()">
                        <option value="">{{ __('All years (last 5)') }}</option>
                        @foreach ($academicYearOptions as $year)
                            <option value="{{ $year }}" @selected($academicYear === $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($academicYear)
                    <p class="kmsar-body" style="margin:0;font-size:13px;color:#475569;align-self:center;">
                        {{ __('Showing data for academic year :year', ['year' => $academicYear]) }}
                    </p>
                @endif
            </div>
        </form>
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
                        <h2 class="kmsar-chart-title">
                            @if ($academicYear)
                                {{ __('Research submitted — :year', ['year' => $academicYear]) }}
                            @else
                                {{ __('Research submitted — last 5 years') }}
                            @endif
                        </h2>
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
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <div class="kmsar-chart-card">
                <div class="kmsar-chart-header">
                    <div>
                        <h2 class="kmsar-chart-title">
                            @if ($academicYear)
                                {{ __('Published research — :year', ['year' => $academicYear]) }}
                            @else
                                {{ __('Published research — last 5 years') }}
                            @endif
                        </h2>
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
                        <h2 class="kmsar-chart-title">
                            @if ($academicYear)
                                {{ __('Presented research — :year', ['year' => $academicYear]) }}
                            @else
                                {{ __('Presented research — last 5 years') }}
                            @endif
                        </h2>
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
            });
        </script>
    @endif
@endpush

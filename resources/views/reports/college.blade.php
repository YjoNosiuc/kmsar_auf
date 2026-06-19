@extends('layouts.app')

@section('title', __('College reports — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Reports') }}
@endsection

@section('content')
    <x-page-header
        :title="__('College research reports')"
        :subtitle="__('Reports are limited to your college. Filter by faculty, SDG, classification, funding, academic year, progress, and approval status.')"
        :breadcrumb="[
            ['label' => __('Reports')],
        ]"
    />

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-6" />
    @endif

    @if ($errors->any())
        <x-alert type="danger" class="mb-6">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @php
        $previewRows = $preview ?? $previewRows ?? collect();
        $exportFilters = array_merge(['include_rejected' => '0'], $filters ?? []);
        $filterHidden = collect($exportFilters)->filter(fn ($v, $k) => $k === 'include_rejected' || ($v !== null && $v !== ''))->all();
        $statusOpts = [
            'proposal' => __('Proposal / abstract'),
            'ongoing' => __('Ongoing'),
            'completed_unpublished' => __('Completed (unpublished)'),
            'presented_internal' => __('Presented (internal)'),
            'presented_external' => __('Presented (external)'),
            'published_non_indexed' => __('Published (non-indexed)'),
            'published_scopus' => __('Published (Scopus / ISI)'),
            'patent_submitted' => __('Patent submitted'),
            'patent_granted' => __('Patent granted'),
        ];
        $classOpts = [
            'self_funded' => __('Self-funded'),
            'internally_funded' => __('Internally funded'),
            'externally_funded' => __('Externally funded'),
            'thesis' => __('Thesis / dissertation'),
            'thesis_dissertation' => __('Thesis/Dissertation of Student/Advisee'),
            'collaboration' => __('Collaboration'),
            'other' => __('Other'),
        ];
        $approvalStageOpts = [
            'draft' => __('Draft'),
            'dean_review' => __('Dean review'),
            'ovpri_review' => __('OVPRI review'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ];
        $facultyOpts = ($faculties ?? collect())->mapWithKeys(fn ($u) => [$u->id => $u->name])->all();
        $page = max(1, (int) ($page ?? 1));
        $perPage = max(10, (int) ($perPage ?? 10));
        $totalCount = (int) ($totalCount ?? $previewRows->count());
        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;
        $rangeStart = $totalCount > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $rangeEnd = min($page * $perPage, $totalCount);
        $paginationQuery = collect(request()->query())->except('page')->all();
        $academicYears = range((int) now()->year + 1, (int) now()->year - 14);
    @endphp

    <x-card :title="__('Filters')" accent="primary" class="mb-8">
        <form method="get" action="{{ route('reports.index') }}" class="space-y-6">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <x-form.select name="faculty" :label="__('Faculty (primary author)')" :placeholder="__('Any faculty')" :options="$facultyOpts" :value="$filters['faculty'] ?? ''" />
                <x-form.select name="research_classification" :label="__('Classification')" :placeholder="__('Any')" :options="$classOpts" :value="$filters['research_classification'] ?? ''" />
                <x-form.select name="status" :label="__('Research progress')" :placeholder="__('Any')" :options="$statusOpts" :value="$filters['status'] ?? ''" />
                <x-form.select name="approval_stage" :label="__('Approval status')" :placeholder="__('Any')" :options="$approvalStageOpts" :value="$filters['approval_stage'] ?? ''" />
                <x-form.select name="sdg" :label="__('SDG')" :placeholder="__('Any SDG')" :options="collect(range(1, 17))->mapWithKeys(fn ($n) => [$n => __('SDG :n', ['n' => $n])])->all()" :value="$filters['sdg'] ?? ''" />
                <x-form.input name="funding_agency" :label="__('Funding agency')" :value="$filters['funding_agency'] ?? ''" :hint="__('Partial match')" />
                <x-form.select name="academic_year" :label="__('Academic year')" :placeholder="__('Any year')" :options="collect($academicYears)->mapWithKeys(fn ($y) => [$y => (string) $y])->all()" :value="$filters['academic_year'] ?? ''" />
                <x-form.input name="date_from" type="date" :label="__('Created from')" :value="$filters['date_from'] ?? ''" />
                <x-form.input name="date_to" type="date" :label="__('Created to')" :value="$filters['date_to'] ?? ''" />
                <x-form.select name="per_page" :label="__('Per page')" :options="['10' => '10', '25' => '25', '50' => '50']" :value="(string) $perPage" />
            </div>
            <div>
                <input type="hidden" name="include_rejected" value="0">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="include_rejected" value="1" @checked(($filters['include_rejected'] ?? '0') === '1') class="rounded border-slate-300 text-[#1E3A8A]">
                    {{ __('Include rejected records in preview and export') }}
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <x-button type="submit" variant="primary">{{ __('Apply filters') }}</x-button>
                <x-button variant="outline" href="{{ route('reports.index') }}">{{ __('Reset') }}</x-button>
            </div>
        </form>
    </x-card>

    <x-card :title="__('Export')" accent="gold" class="mb-8">
        <p class="kmsar-body mb-4 text-sm text-[var(--color-text-secondary)]">{{ __('Download the full result set for the filters above.') }}</p>
        <div class="flex flex-wrap gap-3">
            @foreach (['pdf' => 'primary', 'excel' => 'secondary'] as $fmt => $variant)
                <form method="post" action="{{ route('reports.export') }}" class="inline">
                    @csrf
                    <input type="hidden" name="report_type" value="college">
                    <input type="hidden" name="format" value="{{ $fmt }}">
                    @foreach ($filterHidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="kmsar-btn kmsar-btn--{{ $variant }} kmsar-btn--sm">{{ strtoupper($fmt) }}</button>
                </form>
            @endforeach
        </div>
    </x-card>

    <x-card :title="__('Data preview')" accent="primary">
        <p class="kmsar-body mb-4 text-sm text-[var(--color-text-secondary)]">
            @if ($totalCount > 0)
                {{ __('Showing :start–:end of :total records', ['start' => number_format($rangeStart), 'end' => number_format($rangeEnd), 'total' => number_format($totalCount)]) }}
            @else
                {{ __('No records match the current filters.') }}
            @endif
        </p>
        <div class="kmsar-table-wrap">
            <table class="kmsar-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Reference') }}</th>
                        <th scope="col">{{ __('Title') }}</th>
                        <th scope="col">{{ __('Primary author') }}</th>
                        <th scope="col">{{ __('Research Progress') }}</th>
                        <th scope="col">{{ __('Approval') }}</th>
                        <th scope="col">{{ __('Created') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($previewRows as $row)
                        <tr>
                            <td class="font-medium">{{ $row->reference_number }}</td>
                            <td class="max-w-xs">{{ str($row->title)->limit(64) }}</td>
                            <td>{{ $row->primaryAuthor?->name ?? '—' }}</td>
                            <td class="kmsar-table-cell-sub">{{ $reportGenerator->statusLabel($row->status) }}</td>
                            <td>
                                @if ($row->approval_stage === 'rejected')
                                    <span class="kmsar-badge kmsar-badge--rejected">{{ __('Rejected') }}</span>
                                @else
                                    <span class="kmsar-badge kmsar-badge--draft">{{ $approvalStageOpts[$row->approval_stage] ?? ucwords(str_replace('_', ' ', $row->approval_stage)) }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap kmsar-table-cell-sub">{{ $row->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center kmsar-body" style="padding: var(--space-6);">{{ __('No records match the current filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($totalCount > $perPage)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <span class="text-sm text-slate-500">{{ __('Page :page of :pages', ['page' => $page, 'pages' => $totalPages]) }}</span>
                <div class="flex gap-2">
                    @if ($page > 1)
                        <x-button variant="outline" size="sm" href="{{ route('reports.index', array_merge($paginationQuery, ['page' => $page - 1])) }}">{{ __('Previous') }}</x-button>
                    @endif
                    @if ($page < $totalPages)
                        <x-button variant="primary" size="sm" href="{{ route('reports.index', array_merge($paginationQuery, ['page' => $page + 1])) }}">{{ __('Load more') }}</x-button>
                    @endif
                </div>
            </div>
        @endif
    </x-card>
@endsection

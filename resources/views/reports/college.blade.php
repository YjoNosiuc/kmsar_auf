@extends('layouts.app')

@section('title', __('College reports — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Reports') }}
@endsection

@section('content')
    <x-page-header
        :title="__('College research reports')"
        :subtitle="__('Reports are limited to your college. Filter by faculty lead, dates created, classification, and status.')"
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
        $filterHidden = collect($filters ?? [])->filter(fn ($v) => $v !== null && $v !== '')->all();
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
            'collaboration' => __('Collaboration'),
            'other' => __('Other'),
        ];
        $facultyOpts = $faculties->mapWithKeys(fn ($u) => [$u->id => $u->name])->all();
    @endphp

    <x-card :title="__('Filters')" accent="primary" class="mb-8">
        <form method="get" action="{{ route('reports.index') }}" class="space-y-6">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <x-form.select
                    name="faculty"
                    :label="__('Faculty (primary author)')"
                    :placeholder="__('Any faculty')"
                    :options="$facultyOpts"
                    :value="old('faculty', $filters['faculty'] ?? '')"
                />

                <x-form.select
                    name="registration_type"
                    :label="__('Registration type')"
                    :placeholder="__('Any')"
                    :options="['new' => __('New registration'), 'update' => __('Update')]"
                    :value="old('registration_type', $filters['registration_type'] ?? '')"
                />

                <x-form.select
                    name="research_classification"
                    :label="__('Classification')"
                    :placeholder="__('Any')"
                    :options="$classOpts"
                    :value="old('research_classification', $filters['research_classification'] ?? '')"
                />

                <x-form.select
                    name="status"
                    :label="__('Progress status')"
                    :placeholder="__('Any')"
                    :options="$statusOpts"
                    :value="old('status', $filters['status'] ?? '')"
                />

                <x-form.input
                    name="date_from"
                    type="date"
                    :label="__('Created from')"
                    :value="old('date_from', $filters['date_from'] ?? '')"
                />

                <x-form.input
                    name="date_to"
                    type="date"
                    :label="__('Created to')"
                    :value="old('date_to', $filters['date_to'] ?? '')"
                />
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <x-button type="submit" variant="primary">{{ __('Apply filters') }}</x-button>
                <x-button variant="outline" href="{{ route('reports.index') }}">{{ __('Reset') }}</x-button>
            </div>
        </form>
    </x-card>

    <x-card :title="__('Export')" accent="gold" class="mb-8">
        <p class="kmsar-body mb-4 text-sm text-[var(--color-text-secondary)]">
            {{ __('Download the full result set for the filters above.') }}
        </p>
        <div class="flex flex-wrap gap-3">
            <form method="post" action="{{ route('reports.export') }}" class="inline">
                @csrf
                <input type="hidden" name="report_type" value="college">
                <input type="hidden" name="format" value="pdf">
                @foreach ($filterHidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--sm">{{ __('PDF') }}</button>
            </form>
            <form method="post" action="{{ route('reports.export') }}" class="inline">
                @csrf
                <input type="hidden" name="report_type" value="college">
                <input type="hidden" name="format" value="excel">
                @foreach ($filterHidden as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <button type="submit" class="kmsar-btn kmsar-btn--secondary kmsar-btn--sm">{{ __('Excel') }}</button>
            </form>
        </div>
    </x-card>

    <x-card :title="__('Data preview')" accent="primary" :count="$previewRows->count()">
        <p class="kmsar-body mb-4 text-sm text-[var(--color-text-secondary)]">
            {{ __('Showing up to 25 records matching filters.') }}
        </p>

        <div class="kmsar-table-wrap">
            <table class="kmsar-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Reference') }}</th>
                        <th scope="col">{{ __('Title') }}</th>
                        <th scope="col">{{ __('Primary author') }}</th>
                        <th scope="col">{{ __('Progress status') }}</th>
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
                            <td class="whitespace-nowrap kmsar-table-cell-sub">{{ $row->created_at->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center kmsar-body" style="padding: var(--space-6);">
                                {{ __('No records match the current filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection

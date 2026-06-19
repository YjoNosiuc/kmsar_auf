@extends('layouts.app')

@section('title', __('Research details — Step 1'))

@section('navbar-context')
    {{ __('Faculty · Research registration') }}
@endsection

@section('content')
    <x-page-header
        :title="__('Research details')"
        :subtitle="__('Step 1 of 3 · Describe your research')"
        :breadcrumb="[
            ['label' => __('My Research'), 'route' => 'research.index'],
            ['label' => __('Research details')],
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

    @include('faculty.research.partials.registration-stepper', ['currentStep' => 1, 'research' => $research])

    <x-card :title="__('Research information')" accent="primary">
        <form
            method="post"
            action="{{ route('research.wizard.details.save', $research) }}"
            class="space-y-6"
        >
            @csrf
            @method('PUT')

            <input type="hidden" name="registration_type" value="{{ old('registration_type', $research->registration_type) }}" />

            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-draft-bg)] px-4 py-3 text-sm text-[var(--color-text-secondary)]">
                <strong class="text-[var(--color-text-primary)]">{{ __('Reference') }}:</strong>
                {{ $research->reference_number }}
            </div>

            @php
                $currentOutputs = old('expected_output')
                    ? (array) old('expected_output')
                    : ($research->expectedOutputKeys());
                $sdgNames = [
                    1 => 'No Poverty',
                    2 => 'Zero Hunger',
                    3 => 'Good Health and Well-being',
                    4 => 'Quality Education',
                    5 => 'Gender Equality',
                    6 => 'Clean Water and Sanitation',
                    7 => 'Affordable and Clean Energy',
                    8 => 'Decent Work and Economic Growth',
                    9 => 'Industry, Innovation and Infrastructure',
                    10 => 'Reduced Inequalities',
                    11 => 'Sustainable Cities and Communities',
                    12 => 'Responsible Consumption and Production',
                    13 => 'Climate Action',
                    14 => 'Life Below Water',
                    15 => 'Life on Land',
                    16 => 'Peace, Justice and Strong Institutions',
                    17 => 'Partnerships for the Goals',
                ];
                $currentSdgs = old('sdg_tags')
                    ? array_values(array_map('intval', is_array(old('sdg_tags')) ? old('sdg_tags') : (json_decode(old('sdg_tags'), true) ?? [])))
                    : array_values(array_map('intval', is_array($research->sdg_tags) ? $research->sdg_tags : (json_decode($research->sdg_tags ?? '[]', true) ?? [])));
            @endphp

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="grid-column:1/-1;">
                    <div class="kmsar-form-group">
                        <label for="field_title" class="kmsar-form-label">
                            {{ __('Title') }}
                            <span class="kmsar-form-required" aria-hidden="true">*</span>
                        </label>
                        <textarea
                            name="title"
                            id="field_title"
                            rows="4"
                            required
                            @class(['kmsar-textarea', 'kmsar-input--error' => $errors->has('title')])
                            style="text-transform: none;"
                        >{{ old('title', $research->title) }}</textarea>
                        @if ($errors->has('title'))
                            <p class="kmsar-form-error" id="field_title-error" role="alert">{{ $errors->first('title') }}</p>
                        @endif
                    </div>
                </div>

                <div style="grid-column:1/-1;">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label">{{ __('Mother College / Unit') }} <span class="kmsar-form-required">*</span></label>
                        <select name="mother_college_id" class="kmsar-select" required>
                            @foreach ($colleges as $college)
                                <option
                                    value="{{ $college->id }}"
                                    {{ (string) old('mother_college_id', $research->mother_college_id ?? auth()->user()->college_id) === (string) $college->id ? 'selected' : '' }}
                                >
                                    {{ $college->code }} — {{ $college->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('mother_college_id')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div style="grid-column:1/-1;">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label">{{ __('Other College/Unit Affiliation') }} <span style="font-size:11px;color:#94A3B8;">({{ __('optional') }})</span></label>
                        <select name="other_college_id" class="kmsar-select">
                            <option value="">— {{ __('None') }} —</option>
                            @foreach ($colleges as $college)
                                <option
                                    value="{{ $college->id }}"
                                    {{ (string) old('other_college_id', $research->other_college_id) === (string) $college->id ? 'selected' : '' }}
                                >
                                    {{ $college->code }} — {{ $college->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="kmsar-form-hint">{{ __('Select if this research involves another AUF college or unit.') }}</p>
                        @error('other_college_id')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-form.select
                    name="research_classification"
                    :label="__('Research classification')"
                    :placeholder="__('Select classification')"
                    :options="[
                        'self_funded' => __('Self-funded'),
                        'internally_funded' => __('Internally funded'),
                        'externally_funded' => __('Externally funded'),
                        'thesis' => __('Thesis / dissertation'),
                        'collaboration' => __('Collaboration'),
                        'other' => __('Other'),
                    ]"
                    :value="old('research_classification', $research->research_classification)"
                    :error="$errors->first('research_classification')"
                    required
                />

                <x-form.input
                    name="funding_agency"
                    :label="__('Funding agency (optional)')"
                    :value="old('funding_agency', $research->funding_agency)"
                    :error="$errors->first('funding_agency')"
                    :hint="__('Examples: DOST, DOH, CHED, DTI')"
                />

                <div style="grid-column:1/-1;">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label">{{ __('Expected Output') }} <span class="kmsar-form-required">*</span></label>
                        <div style="display:flex;flex-direction:column;gap:10px;padding:14px;border:1px solid #E2E8F0;border-radius:8px;background:#F8FAFC;">

                            @foreach ([
                                'publication' => 'Publication (Journal / Conference Paper)',
                                'patent' => 'Patent / Intellectual Property',
                                'policy_brief' => 'Policy Brief',
                            ] as $value => $label)
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;color:#0F172A;">
                                    <input
                                        type="checkbox"
                                        name="expected_output[]"
                                        value="{{ $value }}"
                                        {{ in_array($value, $currentOutputs, true) ? 'checked' : '' }}
                                        style="width:16px;height:16px;accent-color:#1E3A8A;cursor:pointer;"
                                    >
                                    {{ __($label) }}
                                </label>
                            @endforeach

                            <label
                                style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;color:#0F172A;flex-wrap:wrap;"
                                x-data="{ otherChecked: {{ in_array('other', $currentOutputs, true) ? 'true' : 'false' }} }"
                            >
                                <input
                                    type="checkbox"
                                    name="expected_output[]"
                                    value="other"
                                    {{ in_array('other', $currentOutputs, true) ? 'checked' : '' }}
                                    x-model="otherChecked"
                                    style="width:16px;height:16px;accent-color:#1E3A8A;cursor:pointer;"
                                >
                                {{ __('Others') }}
                                <input
                                    type="text"
                                    name="expected_output_other"
                                    x-show="otherChecked"
                                    value="{{ old('expected_output_other', $research->expected_output_other) }}"
                                    placeholder="{{ __('Please specify...') }}"
                                    style="flex:1;min-width:160px;padding:6px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;font-family:inherit;text-transform: uppercase"
                                >
                            </label>
                        </div>
                        <p class="kmsar-form-hint">{{ __('Select all that apply.') }}</p>
                        @error('expected_output')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                        @error('expected_output_other')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <x-form.input
                    type="date"
                    name="start_date"
                    :label="__('Start date')"
                    :value="old('start_date', $research->start_date?->format('Y-m-d'))"
                    :error="$errors->first('start_date')"
                    required
                />

                <x-form.input
                    type="date"
                    name="estimated_completion_date"
                    :label="__('Estimated completion date')"
                    :value="old('estimated_completion_date', $research->estimated_completion_date?->format('Y-m-d'))"
                    :error="$errors->first('estimated_completion_date')"
                    required
                />

                <div style="grid-column:1/-1;">
                    <x-form.select
                        name="status"
                        :label="__('Research progress status')"
                        :options="[
                            'proposal' => __('Proposal / abstract'),
                            'ongoing' => __('Ongoing'),
                            'completed_unpublished' => __('Completed (unpublished)'),
                            'presented_internal' => __('Presented (internal)'),
                            'presented_external' => __('Presented (external)'),
                            'published_non_indexed' => __('Published (non-indexed)'),
                            'published_scopus' => __('Published (Scopus / ISI)'),
                            'patent_submitted' => __('Patent submitted'),
                            'patent_granted' => __('Patent granted'),
                        ]"
                        :value="old('status', $research->status)"
                        :error="$errors->first('status')"
                        required
                    />
                </div>

                <div style="grid-column:1/-1;">
                    <x-form.sdg-picker
                        name="sdg_tags"
                        :selected="$currentSdgs"
                        :error="$errors->first('sdg_tags')"
                    />
                </div>
            </div>

            <style>
                .sdg-active { background:#1E3A8A !important; color:#fff !important; border-color:#1E3A8A !important; }
                [x-cloak] { display:none !important; }
            </style>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;">
                <a href="{{ route('research.index') }}" class="kmsar-btn kmsar-btn--secondary">{{ __('Back') }}</a>
                <button type="submit" class="kmsar-btn kmsar-btn--primary">{{ __('Continue to authors') }}</button>
            </div>
        </form>
    </x-card>
@endsection

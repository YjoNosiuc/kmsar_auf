{{--
    NOTE: Primary editing for draft research is the registration wizard (Step 1: research.wizard.details,
    then authors, then documents). This standalone edit view remains for the research.edit route and
    POST research.update until that flow is fully retired — do not remove without updating routes/controllers.
--}}
@extends('layouts.app')

@section('title', __('Edit research'))

@section('navbar-context')
    {{ __('Faculty') }}
@endsection

@section('content')
    <x-page-header
        :title="__('Edit research')"
        :subtitle="$research->reference_number"
        :breadcrumb="[
            ['label' => __('My Research'), 'route' => 'research.index'],
            ['label' => $research->reference_number, 'route' => 'research.show', 'parameters' => ['research' => $research]],
            ['label' => __('Edit')],
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

    <x-card :title="__('Research information')" accent="primary">
        <form
            method="post"
            action="{{ route('research.update', $research) }}"
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
                    ? (is_array(old('sdg_tags')) ? old('sdg_tags') : json_decode(old('sdg_tags'), true) ?? [])
                    : (is_array($research->sdg_tags) ? $research->sdg_tags : (json_decode($research->sdg_tags ?? '[]', true) ?? []));
            @endphp

            <x-form.textarea
                name="title"
                :label="__('Title')"
                rows="4"
                :value="old('title', $research->title)"
                :error="$errors->first('title')"
                required
            />

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

            <div class="grid gap-6 sm:grid-cols-2">
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
            </div>

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

            <div class="kmsar-form-group">
                <span class="kmsar-form-label">{{ __('SDG alignment') }}</span>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
                    @foreach ($sdgNames as $num => $name)
                        <label style="position:relative;display:inline-flex;" x-data="{ show: false }">
                            <input
                                type="checkbox"
                                name="sdg_tags[]"
                                value="{{ $num }}"
                                {{ in_array($num, $currentSdgs, true) ? 'checked' : '' }}
                                style="position:absolute;opacity:0;width:0;height:0;"
                                id="sdg_edit_{{ $num }}"
                            >
                            <span
                                @mouseenter="show=true"
                                @mouseleave="show=false"
                                onclick="document.getElementById('sdg_edit_{{ $num }}').click();this.classList.toggle('sdg-active', document.getElementById('sdg_edit_{{ $num }}').checked)"
                                class="{{ in_array($num, $currentSdgs, true) ? 'sdg-active' : '' }}"
                                style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:28px;border-radius:6px;border:1.5px solid #CBD5E1;font-size:11px;font-weight:700;cursor:pointer;background:#fff;color:#475569;transition:all 0.15s;user-select:none;"
                            >
                                SDG {{ $num }}
                            </span>
                            <span
                                x-show="show"
                                x-cloak
                                style="position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);background:#0F172A;color:#fff;font-size:11px;font-weight:500;padding:5px 10px;border-radius:6px;white-space:nowrap;z-index:50;pointer-events:none;box-shadow:0 2px 8px rgba(0,0,0,0.2);"
                            >
                                <strong>SDG {{ $num }}:</strong> {{ $name }}
                                <span style="position:absolute;top:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:#0F172A;"></span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="kmsar-form-hint">{{ __('Select Sustainable Development Goals that apply.') }}</p>
                @error('sdg_tags')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <style>
                .sdg-active { background:#1E3A8A !important; color:#fff !important; border-color:#1E3A8A !important; }
                [x-cloak] { display:none !important; }
            </style>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-[var(--color-border)]">
                <x-button variant="secondary" href="{{ route('research.show', $research) }}">{{ __('Cancel') }}</x-button>
                <x-button type="submit" variant="primary">{{ __('Save changes') }}</x-button>
            </div>
        </form>
    </x-card>
@endsection

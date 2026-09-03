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

    @if (session('warning'))
        <x-alert type="warning" :message="session('warning')" class="mb-6" />
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

    @include('faculty.research.partials.registration-stepper', [
        'currentStep' => 1,
        'research' => $research,
        'step1Complete' => $step1Complete,
        'step2Complete' => $step2Complete,
    ])

    <x-card :title="__('Research information')" accent="primary">
        @php
            $registrationLocked = \App\Support\ResearchStatus::isRegistrationLocked((string) $research->status);
            $currentRegistrationType = old('registration_type', $research->registration_type);
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
            $selectedOtherColleges = old('other_college_id')
                ? array_values(array_map('intval', (array) old('other_college_id')))
                : $research->otherCollegeIds();
            $currentClassification = old('research_classification', $research->research_classification);
            $currentAgendaThemes = old('agenda_themes')
                ? array_values((array) old('agenda_themes'))
                : array_values(is_array($research->agenda_themes) ? $research->agenda_themes : []);
            $classificationOptions = config('kmsar.research_classifications', []);
            $agendaThemeOptions = config('kmsar.agenda_themes', []);
        @endphp

        <form
            method="post"
            action="{{ route('research.wizard.details.save', $research) }}"
            class="space-y-6"
            x-data="{
                motherCollegeId: '{{ (string) old('mother_college_id', $research->mother_college_id ?? auth()->user()->college_id) }}',
                otherCollegeSelected: @js($selectedOtherColleges),
                toggleOtherCollege(id) {
                    if (String(id) === this.motherCollegeId) return;
                    const i = this.otherCollegeSelected.indexOf(id);
                    if (i > -1) this.otherCollegeSelected.splice(i, 1);
                    else this.otherCollegeSelected.push(id);
                    this.otherCollegeSelected.sort((a, b) => a - b);
                },
                isOtherCollegeOn(id) { return this.otherCollegeSelected.includes(id); },
            }"
            x-effect="otherCollegeSelected = otherCollegeSelected.filter(id => String(id) !== motherCollegeId)"
        >
            @csrf
            @method('PUT')

            @if ($registrationLocked)
                <input type="hidden" name="registration_type" value="{{ $research->registration_type }}">
                <div class="rounded-lg border border-[var(--color-warning-border)] bg-[var(--color-warning-bg)] px-4 py-3 text-sm text-[var(--color-text-secondary)] mb-4">
                    {{ __('Registration details are locked while this record is under review or already registered.') }}
                </div>
            @else
                <div class="kmsar-form-group">
                    <span class="kmsar-form-label">{{ __('Registration type') }} <span class="kmsar-form-required">*</span></span>
                    <div style="display:flex;flex-direction:column;gap:10px;padding:14px;border:1px solid #E2E8F0;border-radius:8px;background:#F8FAFC;">
                        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;color:#0F172A;">
                            <input type="radio" name="registration_type" value="new" @checked($currentRegistrationType === 'new') style="margin-top:3px;accent-color:#1E3A8A;">
                            <span>
                                <strong>{{ __('New research') }}</strong>
                            </span>
                        </label>
                        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;color:#0F172A;">
                            <input type="radio" name="registration_type" value="existing" @checked($currentRegistrationType === 'existing') style="margin-top:3px;accent-color:#1E3A8A;">
                            <span>
                                <strong>{{ __('Existing research') }}</strong>
                                <span style="display:block;font-size:12px;color:#64748B;margin-top:2px;">{{ __('Completed research') }}</span>
                            </span>
                        </label>
                    </div>
                    @error('registration_type')
                        <p class="kmsar-form-error">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-draft-bg)] px-4 py-3 text-sm text-[var(--color-text-secondary)]">
                <strong class="text-[var(--color-text-primary)]">{{ __('Reference') }}:</strong>
                {{ $research->reference_number }}
            </div>

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
                        >{{ in_array(mb_strtolower(trim((string) old('title', $research->title))), ['untitled research'], true) ? '' : old('title', $research->title) }}</textarea>
                        @if ($errors->has('title'))
                            <p class="kmsar-form-error" id="field_title-error" role="alert">{{ $errors->first('title') }}</p>
                        @endif
                    </div>
                </div>

                <div style="grid-column:1/-1;">
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="field_mother_college_id">{{ __('Mother College / Unit') }} <span class="kmsar-form-required">*</span></label>
                        <select id="field_mother_college_id" name="mother_college_id" class="kmsar-select" required x-model="motherCollegeId">
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
                        <div class="kmsar-sdg-grid" role="group" aria-label="{{ __('Other college affiliations') }}">
                            @foreach ($colleges as $college)
                                <button
                                    type="button"
                                    class="kmsar-sdg-chip"
                                    :class="{ 'selected': isOtherCollegeOn({{ $college->id }}) }"
                                    :aria-pressed="isOtherCollegeOn({{ $college->id }}) ? 'true' : 'false'"
                                    :disabled="String({{ $college->id }}) === motherCollegeId"
                                    @click="toggleOtherCollege({{ $college->id }})"
                                >
                                    {{ $college->code }} — {{ $college->name }}
                                </button>
                            @endforeach
                        </div>
                        <template x-for="collegeId in otherCollegeSelected" :key="collegeId">
                            <input type="hidden" name="other_college_id[]" :value="collegeId">
                        </template>
                        <p class="kmsar-form-hint">{{ __('Select all other AUF colleges or units involved in this research.') }}</p>
                        @error('other_college_id')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                        @error('other_college_id.*')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div style="grid-column:1/-1;">
                    <div class="kmsar-form-group">
                        <span class="kmsar-form-label">{{ __('Research classification') }} <span class="kmsar-form-required">*</span></span>
                        <div style="display:flex;flex-direction:column;gap:10px;padding:14px;border:1px solid #E2E8F0;border-radius:8px;background:#F8FAFC;"
                            x-data="{ classification: @js($currentClassification), showOther: @js($currentClassification === 'other') }">
                            @foreach ($classificationOptions as $code => $label)
                                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;color:#0F172A;">
                                    <input
                                        type="radio"
                                        name="research_classification"
                                        value="{{ $code }}"
                                        @checked($currentClassification === $code)
                                        x-model="classification"
                                        @change="showOther = ($event.target.value === 'other')"
                                        style="margin-top:3px;accent-color:#1E3A8A;"
                                    >
                                    <span>{{ __($label) }}</span>
                                </label>
                            @endforeach
                            <div x-show="showOther" x-cloak style="margin-left:26px;">
                                <input
                                    type="text"
                                    name="research_classification_other"
                                    value="{{ old('research_classification_other', $research->research_classification_other) }}"
                                    placeholder="{{ __('Please specify...') }}"
                                    class="kmsar-input"
                                    style="width:100%;"
                                >
                            </div>
                        </div>
                        @error('research_classification')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                        @error('research_classification_other')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

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
                                    style="flex:1;min-width:160px;padding:6px 10px;border:1px solid #E2E8F0;border-radius:6px;font-size:12px;font-family:inherit;"
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
                    <div class="kmsar-form-group">
                        <span class="kmsar-form-label">
                            {{ __('Alignment with AUF Research Agenda Theme') }}
                            <span class="kmsar-form-required" aria-hidden="true">*</span>
                        </span>
                        <div style="display:flex;flex-direction:column;gap:10px;padding:14px;border:1px solid #E2E8F0;border-radius:8px;background:#F8FAFC;">
                            @foreach ($agendaThemeOptions as $code => $label)
                                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;color:#0F172A;">
                                    <input
                                        type="checkbox"
                                        name="agenda_themes[]"
                                        value="{{ $code }}"
                                        {{ in_array($code, $currentAgendaThemes, true) ? 'checked' : '' }}
                                        style="width:16px;height:16px;accent-color:#1E3A8A;cursor:pointer;margin-top:2px;"
                                    >
                                    {{ __($label) }}
                                </label>
                            @endforeach
                        </div>
                        <p class="kmsar-form-hint">{{ __('Select all that apply.') }}</p>
                        @error('agenda_themes')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div style="grid-column:1/-1;">
                    <x-form.sdg-picker
                        name="sdg_tags"
                        :selected="$currentSdgs"
                        :error="$errors->first('sdg_tags')"
                        :required="true"
                    />
                </div>
            </div>

            <style>
                .sdg-active { background:#1E3A8A !important; color:#fff !important; border-color:#1E3A8A !important; }
                [x-cloak] { display:none !important; }
            </style>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem; gap:12px; flex-wrap:wrap;">
                <a href="{{ route('research.index') }}" class="kmsar-btn kmsar-btn--secondary">{{ __('Back') }}</a>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" name="save_as_draft" value="1" class="kmsar-btn kmsar-btn--outline">{{ __('Save as draft') }}</button>
                    <button type="submit" class="kmsar-btn kmsar-btn--primary">{{ __('Continue to authors') }}</button>
                </div>
            </div>
        </form>
    </x-card>
@endsection

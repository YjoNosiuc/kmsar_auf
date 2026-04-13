@extends('layouts.app')

@section('title', __('Co-authors — Step 2'))

@section('navbar-context')
    {{ __('Faculty · Research registration') }}
@endsection

@section('content')
    <x-page-header
        :title="__('Co-authors')"
        :subtitle="__('Step 2 of 3 · Add collaborators (optional)')"
        :breadcrumb="[
            ['label' => __('My Research'), 'route' => 'research.index'],
            ['label' => __('Co-authors')],
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

    @include('faculty.research.partials.registration-stepper', ['currentStep' => 2, 'research' => $research])

    @php
        $kmsarSplitAuthorName = static function (?string $full): array {
            $full = trim((string) $full);
            if ($full === '') {
                return ['first' => '', 'middle' => '', 'last' => '', 'suffix' => ''];
            }
            $parts = preg_split('/\s+/u', $full);
            $parts = array_values(array_filter($parts, static fn ($p) => $p !== ''));
            $n = count($parts);
            if ($n === 1) {
                return ['first' => $parts[0], 'middle' => '', 'last' => '', 'suffix' => ''];
            }
            $last = $parts[$n - 1];
            $first = $parts[0];
            $middle = $n > 2 ? implode(' ', array_slice($parts, 1, -1)) : '';

            return ['first' => $first, 'middle' => $middle, 'last' => $last, 'suffix' => ''];
        };

        $primaryFull = old('primary_author_name', $externalPrimary?->name ?? '');
        $primaryParts = $kmsarSplitAuthorName($primaryFull);

        $coauthorsInitial = collect($coauthorsInitial)
            ->map(function (array $row) use ($kmsarSplitAuthorName) {
                $p = $kmsarSplitAuthorName($row['name'] ?? '');
                $firstName = $row['first_name'] ?? $p['first'];
                $middleName = $row['middle_name'] ?? $p['middle'];
                $lastName = $row['last_name'] ?? $p['last'];
                $suffix = $row['suffix'] ?? $p['suffix'];
                $empNo = $row['employee_number'] ?? $row['student_number'] ?? $row['empNo'] ?? '';
                $collegeId = isset($row['college_id']) ? (string) $row['college_id'] : (string) ($row['collegeId'] ?? '');
                $programId = isset($row['program_id']) ? (string) $row['program_id'] : (string) ($row['programId'] ?? '');
                $affiliatedCollegeId = isset($row['affiliated_college_id'])
                    ? (string) $row['affiliated_college_id']
                    : (string) ($row['affiliatedCollegeId'] ?? '');
                $authorType = $row['author_type'] ?? $row['authorType'] ?? 'student';
                foreach (['name', 'first_name', 'last_name', 'middle_name', 'suffix', 'student_number', 'employee_number', 'college_id', 'program_id', 'affiliated_college_id', 'author_type'] as $k) {
                    unset($row[$k]);
                }

                return array_merge($row, [
                    'authorType' => $authorType,
                    'firstName' => $firstName,
                    'middleName' => $middleName,
                    'lastName' => $lastName,
                    'suffix' => $suffix,
                    'empNo' => $empNo,
                    'collegeId' => $collegeId,
                    'programId' => $programId,
                    'affiliatedCollegeId' => $affiliatedCollegeId,
                ]);
            })
            ->values()
            ->all();

        $programsJson = json_encode($programsByCollege);
        $authUserJson = json_encode([
            'name' => auth()->user()->name,
            'employee_number' => auth()->user()->employee_number,
            'college_id' => auth()->user()->college_id,
        ]);
        $stateJson = json_encode([
            'iAmPrimary' => $iAmPrimary,
            'primaryType' => $primaryType,
            'selfAdded' => $selfAdded,
            'coauthors' => $coauthorsInitial,
            'primaryFirstName' => $primaryParts['first'],
            'primaryMiddleName' => $primaryParts['middle'],
            'primaryLastName' => $primaryParts['last'],
            'primarySuffix' => $primaryParts['suffix'],
            'primaryEmpNo' => old('primary_author_employee_number', $externalPrimary?->employee_number ?? ''),
            'primaryEmail' => old('primary_author_email', $externalPrimary?->email ?? ''),
            'primaryInstitution' => old('primary_author_institution', $externalPrimary?->institution ?? ''),
            'primaryAffiliatedCollegeId' => old('primary_author_affiliated_college_id', ''),
        ]);
    @endphp

    <form
        method="post"
        action="{{ route('research.wizard.authors.save', $research) }}"
        x-data="authorManager({{ $programsJson }}, {{ $authUserJson }}, {{ $stateJson }})"
        class="authors-wizard-step"
    >
        @csrf

        <x-card :title="__('Primary author')" accent="primary">
            <div class="authors-primary-author-toggle">
                <label class="authors-primary-author-toggle__label">
                    <input type="checkbox" x-model="iAmPrimary" class="authors-checkbox">
                    <span>{{ __('I am the primary author of this research') }}</span>
                </label>
                <p class="authors-primary-author-toggle__hint">{{ __('Uncheck if a student or another researcher is the primary author') }}</p>
            </div>

            <div x-show="iAmPrimary" x-cloak>
                <div class="authors-primary-chip">
                    <div class="authors-primary-chip__avatar" aria-hidden="true">
                        <span>{{ strtoupper(substr((string) auth()->user()->name, 0, 2)) }}</span>
                    </div>
                    <div class="authors-primary-chip__text">
                        <div class="authors-primary-chip__name">{{ auth()->user()->name }}</div>
                        <div class="authors-primary-chip__meta">{{ auth()->user()->employee_number }} · {{ auth()->user()->college?->name }}</div>
                    </div>
                    <span class="authors-primary-chip__badge">{{ __('Primary Author') }}</span>
                </div>
            </div>

            <div x-show="!iAmPrimary" x-cloak>
                <div class="authors-role-segment authors-role-segment--primary" role="tablist" aria-label="{{ __('Primary author type') }}">
                    <button
                        type="button"
                        role="tab"
                        class="authors-role-segment__tab"
                        :class="{ 'authors-role-segment__tab--active': primaryType === 'student' }"
                        :aria-selected="primaryType === 'student'"
                        @click="primaryType = 'student'"
                    >{{ __('Student') }}</button>
                    <button
                        type="button"
                        role="tab"
                        class="authors-role-segment__tab"
                        :class="{ 'authors-role-segment__tab--active': primaryType === 'employee' }"
                        :aria-selected="primaryType === 'employee'"
                        @click="primaryType = 'employee'"
                    >{{ __('Employee / Researcher') }}</button>
                </div>

                <input type="hidden" name="primary_author_type" x-bind:value="iAmPrimary ? 'self' : primaryType">
                <input type="hidden" name="primary_author_name" :value="primaryAuthorFullName" x-bind:disabled="iAmPrimary">

                <div
                    x-show="primaryType==='student'"
                    x-data="{
                        selectedCollege: @js(old('primary_author_college_id', $externalPrimary?->college_id ? (string) $externalPrimary->college_id : '')),
                        selectedProgram: @js(old('primary_author_program_id', $externalPrimary?->program_id ? (string) $externalPrimary->program_id : '')),
                    }"
                    class="authors-primary-external-fields space-y-4"
                >
                    <div class="kmsar-form-row-3">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_student_first_name">{{ __('First Name') }} <span class="kmsar-form-required">*</span></label>
                            <input id="primary_student_first_name" type="text" name="first_name" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryFirstName"
                                x-bind:disabled="iAmPrimary || primaryType !== 'student'"
                                x-bind:required="!iAmPrimary && primaryType==='student'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_student_last_name">{{ __('Last Name') }} <span class="kmsar-form-required">*</span></label>
                            <input id="primary_student_last_name" type="text" name="last_name" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryLastName"
                                x-bind:disabled="iAmPrimary || primaryType !== 'student'"
                                x-bind:required="!iAmPrimary && primaryType==='student'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_student_middle_name">{{ __('Middle Name') }}</label>
                            <input id="primary_student_middle_name" type="text" name="middle_name" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryMiddleName"
                                x-bind:disabled="iAmPrimary || primaryType !== 'student'">
                        </div>
                    </div>
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="primary_student_suffix">{{ __('Suffix') }}</label>
                        <input id="primary_student_suffix" type="text" name="suffix" class="kmsar-input" placeholder="{{ __('Jr., Sr., III, etc.') }}"
                            style="text-transform: none"
                            x-model="primarySuffix"
                            x-bind:disabled="iAmPrimary || primaryType !== 'student'">
                    </div>
                    <div class="kmsar-form-row-2">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_student_number">{{ __('Student Number') }} <span class="kmsar-form-required">*</span></label>
                            <input id="primary_student_number" type="text" name="student_number" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryEmpNo"
                                x-bind:disabled="iAmPrimary || primaryType !== 'student'"
                                x-bind:required="!iAmPrimary && primaryType==='student'">
                            <input type="hidden" name="primary_author_employee_number" :value="primaryEmpNo" x-bind:disabled="iAmPrimary || primaryType !== 'student'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_student_email">{{ __('Email Address') }} <span class="kmsar-form-required">*</span></label>
                            <input id="primary_student_email" type="email" name="email" class="kmsar-input"
                                x-model="primaryEmail"
                                x-bind:disabled="iAmPrimary || primaryType !== 'student'"
                                x-bind:required="!iAmPrimary && primaryType==='student'">
                            <input type="hidden" name="primary_author_email" :value="primaryEmail" x-bind:disabled="iAmPrimary || primaryType !== 'student'">
                            <p class="kmsar-form-hint">{{ __('AUF or non-AUF email accepted') }}</p>
                        </div>
                    </div>
                    <div class="kmsar-form-row-2">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_student_college">{{ __('College') }} <span class="kmsar-form-required">*</span></label>
                            <select id="primary_student_college" name="college_id" class="kmsar-select" x-model="selectedCollege"
                                @change="selectedProgram = ''"
                                x-bind:disabled="iAmPrimary || primaryType !== 'student'"
                                x-bind:required="!iAmPrimary && primaryType==='student'">
                                <option value="">{{ __('— Select college —') }}</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="primary_author_college_id" :value="selectedCollege" x-bind:disabled="iAmPrimary || primaryType !== 'student'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_student_program">{{ __('Program') }} <span class="kmsar-form-required">*</span></label>
                            <select id="primary_student_program" name="program_id" class="kmsar-select" x-model="selectedProgram"
                                x-bind:disabled="iAmPrimary || primaryType !== 'student' || !selectedCollege"
                                x-bind:required="!iAmPrimary && primaryType==='student'">
                                <option value="">{{ __('Select college first') }}</option>
                                @foreach ($colleges as $college)
                                    @foreach ($college->programs as $program)
                                        <option value="{{ $program->id }}"
                                            x-show="selectedCollege == '{{ $college->id }}'">
                                            {{ $program->name }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                            <input type="hidden" name="primary_author_program_id" :value="selectedProgram" x-bind:disabled="iAmPrimary || primaryType !== 'student'">
                        </div>
                    </div>
                </div>

                <div
                    x-show="primaryType==='employee'"
                    x-data="{
                        selectedCollege: @js(old('primary_author_college_id', $externalPrimary?->college_id ? (string) $externalPrimary->college_id : '')),
                        selectedProgram: @js(old('primary_author_program_id', $externalPrimary?->program_id ? (string) $externalPrimary->program_id : '')),
                    }"
                    class="authors-primary-external-fields space-y-4"
                >
                    <div class="kmsar-form-row-3">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_first_name">{{ __('First Name') }} <span class="kmsar-form-required">*</span></label>
                            <input id="primary_emp_first_name" type="text" name="first_name" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryFirstName"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee'"
                                x-bind:required="!iAmPrimary && primaryType==='employee'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_last_name">{{ __('Last Name') }} <span class="kmsar-form-required">*</span></label>
                            <input id="primary_emp_last_name" type="text" name="last_name" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryLastName"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee'"
                                x-bind:required="!iAmPrimary && primaryType==='employee'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_middle_name">{{ __('Middle Name') }}</label>
                            <input id="primary_emp_middle_name" type="text" name="middle_name" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryMiddleName"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                        </div>
                    </div>
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="primary_emp_suffix">{{ __('Suffix') }}</label>
                        <input id="primary_emp_suffix" type="text" name="suffix" class="kmsar-input" placeholder="{{ __('Jr., Sr., III, etc.') }}"
                            style="text-transform: none"
                            x-model="primarySuffix"
                            x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                    </div>
                    <div class="kmsar-form-row-2">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_number">{{ __('Employee Number') }}</label>
                            <input id="primary_emp_number" type="text" name="employee_number" class="kmsar-input" style="text-transform: uppercase"
                                x-model="primaryEmpNo"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                            <input type="hidden" name="primary_author_employee_number" :value="primaryEmpNo" x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_email">{{ __('Email Address') }} <span class="kmsar-form-required">*</span></label>
                            <input id="primary_emp_email" type="email" name="email" class="kmsar-input"
                                x-model="primaryEmail"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee'"
                                x-bind:required="!iAmPrimary && primaryType==='employee'">
                            <input type="hidden" name="primary_author_email" :value="primaryEmail" x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                        </div>
                    </div>
                    <div class="kmsar-form-row-3">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_mother_college">{{ __('Mother College') }} <span class="kmsar-form-required">*</span></label>
                            <select id="primary_emp_mother_college" name="college_id" class="kmsar-select" x-model="selectedCollege"
                                @change="selectedProgram = ''"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee'"
                                x-bind:required="!iAmPrimary && primaryType==='employee'">
                                <option value="">{{ __('— Select college —') }}</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="primary_author_college_id" :value="selectedCollege" x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_affiliated">{{ __('Affiliated College/Unit') }}</label>
                            <select id="primary_emp_affiliated" name="affiliated_college_id" class="kmsar-select" x-model="primaryAffiliatedCollegeId"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="primary_emp_program">{{ __('Program') }}</label>
                            <select id="primary_emp_program" name="program_id" class="kmsar-select" x-model="selectedProgram"
                                x-bind:disabled="iAmPrimary || primaryType !== 'employee' || !selectedCollege">
                                <option value="">{{ __('Select college first') }}</option>
                                @foreach ($colleges as $college)
                                    @foreach ($college->programs as $program)
                                        <option value="{{ $program->id }}"
                                            x-show="selectedCollege == '{{ $college->id }}'">
                                            {{ $program->name }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                            <input type="hidden" name="primary_author_program_id" :value="selectedProgram" x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                        </div>
                    </div>
                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="primary_emp_institution">{{ __('Institution') }}</label>
                        <input id="primary_emp_institution" type="text" name="institution" class="kmsar-input"
                            placeholder="{{ __('e.g. University of the Philippines') }}"
                            style="text-transform: none"
                            x-model="primaryInstitution"
                            x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                        <input type="hidden" name="primary_author_institution" :value="primaryInstitution" x-bind:disabled="iAmPrimary || primaryType !== 'employee'">
                        <p class="kmsar-form-hint">{{ __('Fill if researcher is from outside AUF') }}</p>
                    </div>
                </div>
            </div>
        </x-card>

        <x-card :title="__('Co-authors')" accent="primary">
            <div class="authors-coauthors-toolbar">
                <div class="authors-coauthors-toolbar__actions">
                    <button type="button" @click="addCoauthor()" class="authors-btn-secondary authors-btn-secondary--muted">
                        + {{ __('Add co-author') }}
                    </button>
                </div>
            </div>

            <p class="authors-coauthors-hint">{{ __('Leave all rows empty if there are no co-authors.') }}</p>

            <template x-for="(author, index) in coauthors" :key="index">
                <div class="coauthor-row authors-coauthor-card">

                    <button type="button" @click="removeCoauthor(index)"
                        x-show="coauthors.length > 1"
                        class="authors-coauthor-remove"
                        aria-label="{{ __('Remove co-author') }}">×</button>

                    <div x-show="!selfAdded || author.isMe" class="authors-me-card">
                        <label class="authors-me-card__label">
                            <input type="checkbox" x-model="author.isMe" @change="fillSelf(index)" class="authors-checkbox">
                            <span>{{ __('This is me — fill my details automatically') }}</span>
                        </label>
                    </div>

                    <div x-show="!author.isMe" class="authors-role-segment authors-role-segment--coauthor" role="tablist" :aria-label="'{{ __('Co-author type') }} ' + (index + 1)">
                        <button
                            type="button"
                            role="tab"
                            class="authors-role-segment__tab"
                            :class="{ 'authors-role-segment__tab--active': author.authorType === 'student' }"
                            :aria-selected="author.authorType === 'student'"
                            @click="author.authorType = 'student'"
                        >{{ __('Student') }}</button>
                        <button
                            type="button"
                            role="tab"
                            class="authors-role-segment__tab"
                            :class="{ 'authors-role-segment__tab--active': author.authorType === 'employee' }"
                            :aria-selected="author.authorType === 'employee'"
                            @click="author.authorType = 'employee'"
                        >{{ __('Employee / Researcher') }}</button>
                    </div>

                    <input type="hidden" :name="'authors[' + index + '][author_type]'" :value="author.authorType">
                    <input type="hidden" :name="'authors[' + index + '][name]'" :value="coauthorFullName(author)">

                    <div x-show="author.authorType==='student'" class="authors-coauthor-fields space-y-4">
                        <div class="kmsar-form-row-3">
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_student_fn_' + index">{{ __('First Name') }} <span class="kmsar-form-required">*</span></label>
                                <input type="text" :id="'coauthor_student_fn_' + index" :name="'authors[' + index + '][first_name]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.firstName"
                                    x-bind:disabled="author.authorType !== 'student'">
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_student_ln_' + index">{{ __('Last Name') }} <span class="kmsar-form-required">*</span></label>
                                <input type="text" :id="'coauthor_student_ln_' + index" :name="'authors[' + index + '][last_name]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.lastName"
                                    x-bind:disabled="author.authorType !== 'student'">
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_student_mn_' + index">{{ __('Middle Name') }}</label>
                                <input type="text" :id="'coauthor_student_mn_' + index" :name="'authors[' + index + '][middle_name]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.middleName"
                                    x-bind:disabled="author.authorType !== 'student'">
                            </div>
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" :for="'coauthor_student_sx_' + index">{{ __('Suffix') }}</label>
                            <input type="text" :id="'coauthor_student_sx_' + index" :name="'authors[' + index + '][suffix]'" class="kmsar-input" placeholder="{{ __('Jr., Sr., III, etc.') }}"
                                style="text-transform: none"
                                x-model="author.suffix"
                                x-bind:disabled="author.authorType !== 'student'">
                        </div>
                        <div class="kmsar-form-row-2">
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_student_sn_' + index">{{ __('Student Number') }} <span class="kmsar-form-required">*</span></label>
                                <input type="text" :id="'coauthor_student_sn_' + index" :name="'authors[' + index + '][student_number]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.empNo"
                                    x-bind:disabled="author.authorType !== 'student'">
                                <input type="hidden" :name="'authors[' + index + '][employee_number]'" :value="author.empNo" x-bind:disabled="author.authorType !== 'student'">
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_student_em_' + index">{{ __('Email Address') }} <span class="kmsar-form-required">*</span></label>
                                <input type="email" :id="'coauthor_student_em_' + index" :name="'authors[' + index + '][email]'" class="kmsar-input"
                                    x-model="author.email"
                                    x-bind:disabled="author.authorType !== 'student'">
                                <p class="kmsar-form-hint">{{ __('AUF or non-AUF email accepted') }}</p>
                            </div>
                        </div>
                        <div class="kmsar-form-row-2">
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_student_col_' + index">{{ __('College') }} <span class="kmsar-form-required">*</span></label>
                                <select :id="'coauthor_student_col_' + index" :name="'authors[' + index + '][college_id]'" x-model="author.collegeId"
                                    @change="author.programId = ''" class="kmsar-select"
                                    x-bind:disabled="author.authorType !== 'student'">
                                    <option value="">{{ __('— Select college —') }}</option>
                                    @foreach ($colleges as $college)
                                        <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_student_pr_' + index">{{ __('Program') }} <span class="kmsar-form-required">*</span></label>
                                <select :id="'coauthor_student_pr_' + index" :name="'authors[' + index + '][program_id]'" x-model="author.programId"
                                    x-bind:disabled="author.authorType !== 'student' || !author.collegeId" class="kmsar-select">
                                    <option value="">{{ __('Select college first') }}</option>
                                    @foreach ($colleges as $college)
                                        @foreach ($college->programs as $program)
                                            <option value="{{ $program->id }}"
                                                x-show="author.collegeId == '{{ $college->id }}'">
                                                {{ $program->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="author.authorType==='employee'" class="authors-coauthor-fields space-y-4">
                        <div class="kmsar-form-row-3">
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_fn_' + index">{{ __('First Name') }} <span class="kmsar-form-required">*</span></label>
                                <input type="text" :id="'coauthor_emp_fn_' + index" :name="'authors[' + index + '][first_name]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.firstName"
                                    x-bind:disabled="author.authorType !== 'employee'">
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_ln_' + index">{{ __('Last Name') }} <span class="kmsar-form-required">*</span></label>
                                <input type="text" :id="'coauthor_emp_ln_' + index" :name="'authors[' + index + '][last_name]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.lastName"
                                    x-bind:disabled="author.authorType !== 'employee'">
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_mn_' + index">{{ __('Middle Name') }}</label>
                                <input type="text" :id="'coauthor_emp_mn_' + index" :name="'authors[' + index + '][middle_name]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.middleName"
                                    x-bind:disabled="author.authorType !== 'employee'">
                            </div>
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" :for="'coauthor_emp_sx_' + index">{{ __('Suffix') }}</label>
                            <input type="text" :id="'coauthor_emp_sx_' + index" :name="'authors[' + index + '][suffix]'" class="kmsar-input" placeholder="{{ __('Jr., Sr., III, etc.') }}"
                                style="text-transform: none"
                                x-model="author.suffix"
                                x-bind:disabled="author.authorType !== 'employee'">
                        </div>
                        <div class="kmsar-form-row-2">
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_en_' + index">{{ __('Employee Number') }}</label>
                                <input type="text" :id="'coauthor_emp_en_' + index" :name="'authors[' + index + '][employee_number]'" class="kmsar-input" style="text-transform: uppercase"
                                    x-model="author.empNo"
                                    x-bind:disabled="author.authorType !== 'employee'">
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_em_' + index">{{ __('Email Address') }} <span class="kmsar-form-required">*</span></label>
                                <input type="email" :id="'coauthor_emp_em_' + index" :name="'authors[' + index + '][email]'" class="kmsar-input"
                                    x-model="author.email"
                                    x-bind:disabled="author.authorType !== 'employee'">
                            </div>
                        </div>
                        <div class="kmsar-form-row-3">
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_mc_' + index">{{ __('Mother College') }} <span class="kmsar-form-required">*</span></label>
                                <select :id="'coauthor_emp_mc_' + index" :name="'authors[' + index + '][college_id]'" x-model="author.collegeId"
                                    @change="author.programId = ''" class="kmsar-select"
                                    x-bind:disabled="author.authorType !== 'employee'">
                                    <option value="">{{ __('— Select college —') }}</option>
                                    @foreach ($colleges as $college)
                                        <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_ac_' + index">{{ __('Affiliated College/Unit') }}</label>
                                <select :id="'coauthor_emp_ac_' + index" :name="'authors[' + index + '][affiliated_college_id]'" x-model="author.affiliatedCollegeId" class="kmsar-select"
                                    x-bind:disabled="author.authorType !== 'employee'">
                                    <option value="">{{ __('— None —') }}</option>
                                    @foreach ($colleges as $college)
                                        <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="kmsar-form-group">
                                <label class="kmsar-form-label" :for="'coauthor_emp_pr_' + index">{{ __('Program') }}</label>
                                <select :id="'coauthor_emp_pr_' + index" :name="'authors[' + index + '][program_id]'" x-model="author.programId"
                                    x-bind:disabled="author.authorType !== 'employee' || !author.collegeId" class="kmsar-select">
                                    <option value="">{{ __('Select college first') }}</option>
                                    @foreach ($colleges as $college)
                                        @foreach ($college->programs as $program)
                                            <option value="{{ $program->id }}"
                                                x-show="author.collegeId == '{{ $college->id }}'">
                                                {{ $program->name }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" :for="'coauthor_emp_inst_' + index">{{ __('Institution') }}</label>
                            <input type="text" :id="'coauthor_emp_inst_' + index" :name="'authors[' + index + '][institution]'" x-model="author.institution"
                                class="kmsar-input" placeholder="{{ __('e.g. University of the Philippines') }}"
                                style="text-transform: none"
                                x-bind:disabled="author.authorType !== 'employee'">
                            <p class="kmsar-form-hint">{{ __('Fill if researcher is from outside AUF') }}</p>
                        </div>
                    </div>

                </div>
            </template>
        </x-card>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;">
            <a href="{{ route('research.wizard.details', $research) }}" class="kmsar-btn kmsar-btn--secondary">{{ __('Back') }}</a>
            <button type="submit" class="kmsar-btn kmsar-btn--primary">{{ __('Continue to documents') }}</button>
        </div>
    </form>

    <style>
        .authors-wizard-step {
            --authors-blue: #1a56db;
            --authors-blue-soft: rgba(26, 86, 219, 0.14);
            --authors-border: #d1d5db;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .authors-wizard-step .kmsar-card-title {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .authors-primary-author-toggle {
            background: #f9fafb;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
        }

        .authors-primary-author-toggle__label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin: 0;
        }

        .authors-primary-author-toggle__hint {
            font-size: 12px;
            color: #9ca3af;
            margin: 8px 0 0 28px;
        }

        .authors-checkbox {
            width: 17px;
            height: 17px;
            margin-top: 2px;
            flex-shrink: 0;
            accent-color: var(--authors-blue);
            cursor: pointer;
        }

        .authors-primary-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #eff6ff;
            border-radius: 8px;
            border: 1px solid #dbeafe;
            margin-bottom: 0;
        }

        .authors-primary-chip__avatar {
            width: 38px;
            height: 38px;
            background: var(--authors-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .authors-primary-chip__avatar span {
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }

        .authors-primary-chip__name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .authors-primary-chip__meta {
            font-size: 12px;
            color: #4b5563;
        }

        .authors-primary-chip__badge {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #bfdbfe;
        }

        .authors-role-segment {
            display: flex;
            padding: 4px;
            background: #f3f4f6;
            border-radius: 8px;
            gap: 0;
            margin-bottom: 16px;
        }

        .authors-role-segment--coauthor {
            margin-bottom: 16px;
        }

        .authors-role-segment__tab {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            transition: color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
        }

        .authors-role-segment__tab:hover {
            color: #374151;
        }

        .authors-role-segment__tab--active {
            background: #fff;
            color: #111827;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .authors-role-segment__tab:focus-visible {
            outline: 2px solid var(--authors-blue);
            outline-offset: 2px;
        }

        .authors-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .authors-label--optional {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: none;
            color: #9ca3af;
        }

        .authors-fields-grid {
            display: grid;
            gap: 16px;
        }

        .authors-fields-grid--two {
            grid-template-columns: 1fr 1fr;
        }

        .authors-fields-grid--three {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .authors-field--full {
            grid-column: 1 / -1;
        }

        .authors-wizard-step .kmsar-input,
        .authors-wizard-step .kmsar-select {
            height: 40px;
            min-height: 40px;
            border: 1px solid var(--authors-border);
            border-radius: 6px;
            box-sizing: border-box;
        }

        .authors-wizard-step .kmsar-input:focus,
        .authors-wizard-step .kmsar-select:focus {
            border-color: var(--authors-blue);
            box-shadow: 0 0 0 3px var(--authors-blue-soft);
        }

        .authors-coauthors-toolbar {
            margin-bottom: 8px;
        }

        .authors-coauthors-toolbar__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .authors-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: #eff6ff;
            color: var(--authors-blue);
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }

        .authors-btn-secondary--muted {
            background: #f9fafb;
            color: #4b5563;
            border-color: #e5e7eb;
        }

        .authors-coauthors-hint {
            font-size: 12px;
            color: #9ca3af;
            margin: 0 0 16px;
        }

        .authors-coauthor-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            background: #fff;
            position: relative;
        }

        .authors-coauthor-remove {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-family: inherit;
        }

        .authors-me-card {
            background: #f9fafb;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
        }

        .authors-me-card__label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
            font-size: 13px;
            color: #374151;
            margin: 0;
        }

        .authors-wizard-step__footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-top: 8px;
            margin-top: 4px;
        }

        .authors-wizard-step__btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 20px;
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
            background: transparent;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            text-decoration: none;
            font-family: inherit;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }

        .authors-wizard-step__btn-back:hover {
            background: #f9fafb;
            color: #374151;
            border-color: #9ca3af;
        }

        .authors-wizard-step__btn-continue {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 22px;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: var(--authors-blue);
            border: 1px solid var(--authors-blue);
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s ease, border-color 0.15s ease, filter 0.15s ease;
        }

        .authors-wizard-step__btn-continue:hover {
            filter: brightness(1.05);
        }

        .authors-wizard-step__btn-continue:focus-visible {
            outline: 2px solid var(--authors-blue);
            outline-offset: 2px;
        }

        @media (max-width: 1024px) {
            .authors-fields-grid--three,
            .authors-fields-grid--two {
                grid-template-columns: 1fr !important;
            }

            .authors-field--full {
                grid-column: 1;
            }
        }
    </style>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('authorManager', (programsByCollege, authUser, initial) => ({
                iAmPrimary: initial.iAmPrimary,
                primaryType: initial.primaryType ?? 'student',
                selfAdded: initial.selfAdded ?? false,
                coauthors: initial.coauthors,

                primaryFirstName: initial.primaryFirstName ?? '',
                primaryMiddleName: initial.primaryMiddleName ?? '',
                primaryLastName: initial.primaryLastName ?? '',
                primarySuffix: initial.primarySuffix ?? '',
                primaryEmpNo: initial.primaryEmpNo ?? '',
                primaryEmail: initial.primaryEmail ?? '',
                primaryInstitution: initial.primaryInstitution ?? '',
                primaryAffiliatedCollegeId: initial.primaryAffiliatedCollegeId ?? '',

                get primaryAuthorFullName() {
                    const f = (this.primaryFirstName || '').trim();
                    const m = (this.primaryMiddleName || '').trim();
                    const l = (this.primaryLastName || '').trim();
                    const s = (this.primarySuffix || '').trim();
                    return [f, m, l, s].filter(Boolean).join(' ');
                },

                coauthorFullName(author) {
                    const f = (author.firstName || '').trim();
                    const m = (author.middleName || '').trim();
                    const l = (author.lastName || '').trim();
                    const s = (author.suffix || '').trim();
                    return [f, m, l, s].filter(Boolean).join(' ');
                },

                getProgramsForCollege(collegeId) {
                    if (!collegeId) return [];
                    return programsByCollege[collegeId] || [];
                },

                addCoauthor() {
                    this.coauthors.push({
                        isMe: false,
                        authorType: 'student',
                        firstName: '',
                        middleName: '',
                        lastName: '',
                        suffix: '',
                        empNo: '',
                        institution: '',
                        collegeId: '',
                        programId: '',
                        affiliatedCollegeId: '',
                        email: '',
                    });
                },

                removeCoauthor(index) {
                    if (this.coauthors[index].isMe) this.selfAdded = false;
                    this.coauthors.splice(index, 1);
                },

                fillSelf(index) {
                    const a = this.coauthors[index];
                    if (a.isMe) {
                        this.selfAdded = true;
                        const parts = String(authUser.name || '').trim().split(/\s+/).filter(Boolean);
                        a.firstName = parts[0] || '';
                        a.lastName = parts.length > 1 ? parts[parts.length - 1] : '';
                        a.middleName = parts.length > 2 ? parts.slice(1, -1).join(' ') : '';
                        a.suffix = '';
                        a.empNo = authUser.employee_number || '';
                        a.collegeId = authUser.college_id ? String(authUser.college_id) : '';
                        a.programId = '';
                        a.affiliatedCollegeId = '';
                        a.email = '';
                        a.institution = '';
                        a.authorType = 'employee';
                    } else {
                        this.selfAdded = false;
                        a.firstName = '';
                        a.middleName = '';
                        a.lastName = '';
                        a.suffix = '';
                        a.empNo = '';
                        a.collegeId = '';
                        a.programId = '';
                        a.email = '';
                    }
                },

                addMyselfAsCoauthor() {
                    const parts = String(authUser.name || '').trim().split(/\s+/).filter(Boolean);
                    this.coauthors.push({
                        isMe: true,
                        authorType: 'employee',
                        firstName: parts[0] || '',
                        middleName: parts.length > 2 ? parts.slice(1, -1).join(' ') : '',
                        lastName: parts.length > 1 ? parts[parts.length - 1] : '',
                        suffix: '',
                        empNo: authUser.employee_number || '',
                        institution: '',
                        collegeId: authUser.college_id ? String(authUser.college_id) : '',
                        programId: '',
                        affiliatedCollegeId: '',
                        email: '',
                    });
                    this.selfAdded = true;
                },
            }));
        });
    </script>
@endpush

@extends('layouts.app')
@section('title', 'My Profile')
@section('navbar-context', 'My Profile')

@section('content')

@if(session('success'))
    <x-alert type="success"
             class="mb-4">
        {{ session('success') }}
    </x-alert>
@endif

{{-- Page header --}}
<div class="kmsar-page-header"
     style="margin-bottom:var(--space-5);">
    <div>
        <h1 class="kmsar-h2">My Profile</h1>
        <p class="kmsar-body">
            Manage your personal information and password
        </p>
    </div>
</div>

<div class="kmsar-two-col"
     style="align-items:flex-start;">

    {{-- LEFT — Personal info --}}
    <x-card title="Personal information">
        <form method="POST"
              action="{{ route('profile.update') }}"
              x-data>
            @csrf
            @method('PATCH')

            @if($errors->profile->any())
                <x-alert type="danger" class="mb-4">
                    <ul style="margin:0;
                               padding-left:1.125rem;
                               font-size:var(--text-sm);">
                        @foreach($errors->profile->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <div class="kmsar-form-row-3">
                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_first_name">
                        First Name
                        <span class="kmsar-form-required" aria-hidden="true">*</span>
                    </label>
                    @if ($nameFieldsLocked)
                        <input type="text"
                               id="profile_first_name"
                               class="kmsar-input"
                               value="{{ $user->first_name }}"
                               disabled
                               style="background:var(--color-surface);
                                      color:var(--color-text-muted);
                                      cursor:not-allowed;">
                    @else
                        <input type="text"
                               name="first_name"
                               id="profile_first_name"
                               class="kmsar-input {{ $errors->profile->has('first_name') ? 'kmsar-input--error' : '' }}"
                               value="{{ old('first_name', $user->first_name) }}"
                               required>
                        @error('first_name', 'profile')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_last_name">
                        Last Name
                        <span class="kmsar-form-required" aria-hidden="true">*</span>
                    </label>
                    @if ($nameFieldsLocked)
                        <input type="text"
                               id="profile_last_name"
                               class="kmsar-input"
                               value="{{ $user->last_name }}"
                               disabled
                               style="background:var(--color-surface);
                                      color:var(--color-text-muted);
                                      cursor:not-allowed;">
                    @else
                        <input type="text"
                               name="last_name"
                               id="profile_last_name"
                               class="kmsar-input {{ $errors->profile->has('last_name') ? 'kmsar-input--error' : '' }}"
                               value="{{ old('last_name', $user->last_name) }}"
                               required>
                        @error('last_name', 'profile')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_middle_name">
                        Middle Name
                    </label>
                    <input type="text"
                           name="middle_name"
                           id="profile_middle_name"
                           class="kmsar-input {{ $errors->profile->has('middle_name') ? 'kmsar-input--error' : '' }}"
                           value="{{ old('middle_name', $user->middle_name) }}">
                    @error('middle_name', 'profile')
                        <p class="kmsar-form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_suffix">
                    Suffix
                </label>
                <input type="text"
                       name="suffix"
                       id="profile_suffix"
                       class="kmsar-input {{ $errors->profile->has('suffix') ? 'kmsar-input--error' : '' }}"
                       value="{{ old('suffix', $user->suffix) }}"
                       placeholder="Jr., Sr., III, etc.">
                @error('suffix', 'profile')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label">Email Address</label>
                <input type="email"
                       id="profile_email"
                       class="kmsar-input"
                       value="{{ $user->email }}"
                       disabled
                       style="background:var(--color-surface);
                              color:var(--color-text-muted);
                              cursor:not-allowed;">
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_employee_number">ID Number</label>
                <input type="text"
                       name="employee_number"
                       id="profile_employee_number"
                       class="kmsar-input {{ $errors->profile->has('employee_number') ? 'kmsar-input--error' : '' }}"
                       value="{{ old('employee_number', $user->employee_number) }}"
                       inputmode="numeric"
                       pattern="[0-9]*"
                       maxlength="10"
                       autocomplete="off"
                       x-on:input="$event.target.value = $event.target.value.replace(/[^0-9]/g, '').slice(0, 10)">
                @error('employee_number', 'profile')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div x-data="profileCollegeProgram(@js($profileCollegeProgramInitial))">
                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_college_id">College/Office</label>
                    <select
                        id="profile_college_id"
                        name="college_id"
                        class="kmsar-select {{ $errors->profile->has('college_id') ? 'kmsar-input--error' : '' }}"
                        x-model="selectedCollegeId"
                        x-on:change="loadPrograms($event.target.value, true)"
                    >
                        <option value="">{{ __('— Select College/Office —') }}</option>
                        @foreach ($colleges as $college)
                            <option value="{{ $college->id }}">
                                {{ $college->code }} — {{ $college->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('college_id', 'profile')
                        <p class="kmsar-form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="kmsar-form-group">
                    <label class="kmsar-form-label" for="profile_program_id">Program/Dept</label>
                    <input type="hidden" name="program_id" x-bind:value="selectedProgramId || ''">
                    <select
                        id="profile_program_id"
                        class="kmsar-select {{ $errors->profile->has('program_id') ? 'kmsar-input--error' : '' }}"
                        x-model="selectedProgramId"
                        x-ref="programSelect"
                        x-bind:disabled="!selectedCollegeId"
                    >
                        <option value="">{{ __('— Select Program/Dept (optional) —') }}</option>
                        @foreach ($programsForSelect as $program)
                            <option value="{{ $program['id'] }}">
                                {{ $program['code'] }} — {{ $program['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('program_id', 'profile')
                        <p class="kmsar-form-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label">User Type</label>
                <input type="text"
                       class="kmsar-input"
                       value="{{ match ($user->user_type) {
                           'faculty' => 'Faculty',
                           'staff' => 'Staff',
                           'student' => 'Student',
                           'external_affiliate' => 'External Affiliate',
                           default => '—',
                       } }}"
                       disabled
                       style="background:var(--color-surface);
                              color:var(--color-text-muted);
                              cursor:not-allowed;">
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label">Role</label>
                <input type="text"
                       class="kmsar-input"
                       value="{{ ucwords(str_replace('_', ' ', $user->roles->first()?->name ?? '—')) }}"
                       disabled
                       style="background:var(--color-surface);
                              color:var(--color-text-muted);
                              cursor:not-allowed;">
            </div>

            <div style="display:flex;
                        justify-content:flex-end;
                        margin-top:1.5rem;
                        padding-top:1rem;
                        border-top:1px solid var(--color-border);">
                <button type="submit"
                        class="kmsar-btn kmsar-btn--primary">
                    Save changes
                </button>
            </div>
        </form>
    </x-card>

    {{-- RIGHT — Change password --}}
    <x-card title="Change password">
        <form method="POST"
              action="{{ route('profile.password') }}">
            @csrf
            @method('PATCH')

            @if($errors->password->any())
                <x-alert type="danger" class="mb-4">
                    <ul style="margin:0;
                               padding-left:1.125rem;
                               font-size:var(--text-sm);">
                        @foreach($errors->password->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_current_password">
                    Current Password
                    <span class="kmsar-form-required" aria-hidden="true">*</span>
                </label>
                <input type="password"
                       name="current_password"
                       id="profile_current_password"
                       class="kmsar-input {{ $errors->password->has('current_password') ? 'kmsar-input--error' : '' }}"
                       style="text-transform: none;"
                       required
                       autocomplete="current-password">
                @error('current_password', 'password')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_password">
                    New Password
                    <span class="kmsar-form-required" aria-hidden="true">*</span>
                </label>
                <input type="password"
                       name="password"
                       id="profile_password"
                       class="kmsar-input {{ $errors->password->has('password') ? 'kmsar-input--error' : '' }}"
                       placeholder="Min. 8 characters"
                       style="text-transform: none;"
                       required
                       autocomplete="new-password">
                @error('password', 'password')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="kmsar-form-group">
                <label class="kmsar-form-label" for="profile_password_confirmation">
                    Confirm New Password
                    <span class="kmsar-form-required" aria-hidden="true">*</span>
                </label>
                <input type="password"
                       name="password_confirmation"
                       id="profile_password_confirmation"
                       class="kmsar-input {{ $errors->password->has('password_confirmation') ? 'kmsar-input--error' : '' }}"
                       placeholder="Repeat new password"
                       style="text-transform: none;"
                       required
                       autocomplete="new-password">
                @error('password_confirmation', 'password')
                    <p class="kmsar-form-error">{{ $message }}</p>
                @enderror
            </div>

            <div style="display:flex;
                        justify-content:flex-end;
                        margin-top:1.5rem;
                        padding-top:1rem;
                        border-top:1px solid var(--color-border);">
                <button type="submit"
                        class="kmsar-btn kmsar-btn--primary">
                    Change password
                </button>
            </div>
        </form>
    </x-card>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('profileCollegeProgram', (config = {}) => ({
        selectedCollegeId: config.selectedCollegeId ?? '',
        selectedProgramId: config.selectedProgramId ?? '',
        programs: Array.isArray(config.programs) ? config.programs : [],
        programsUrl: config.programsUrl ?? '',
        syncProgramOptions(programs) {
            const select = this.$refs.programSelect;
            if (!select) {
                return;
            }
            const placeholder = select.options[0];
            select.replaceChildren(placeholder);
            programs.forEach((program) => {
                const option = document.createElement('option');
                option.value = String(program.id);
                option.textContent = `${program.code} — ${program.name}`;
                select.appendChild(option);
            });
        },
        loadPrograms(collegeId, clearSelection = false) {
            if (clearSelection) {
                this.selectedProgramId = '';
            }
            const keepProgramId = this.selectedProgramId;
            if (!collegeId) {
                this.programs = [];
                this.syncProgramOptions([]);
                return;
            }
            fetch(`${this.programsUrl}?college_id=${encodeURIComponent(collegeId)}`, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((data) => {
                    this.programs = Array.isArray(data) ? data : [];
                    this.syncProgramOptions(this.programs);
                    if (
                        !clearSelection
                        && keepProgramId
                        && this.programs.some((program) => String(program.id) === String(keepProgramId))
                    ) {
                        this.selectedProgramId = String(keepProgramId);
                    }
                });
        },
    }));
});
</script>
@endpush

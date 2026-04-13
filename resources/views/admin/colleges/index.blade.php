@extends('layouts.app')

@section('title', __('Colleges & programs — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Admin') }}
@endsection

@php
    $editCollegeInitial =
        $errors->any() && old('_form') === 'edit_college'
            ? [
                'id' => (int) old('edit_college_id'),
                'name' => old('name', ''),
                'code' => old('code', ''),
                'is_active' => filter_var(old('is_active', '1'), FILTER_VALIDATE_BOOLEAN),
            ]
            : [];

    $editProgramInitial =
        $errors->any() && old('_form') === 'edit_program'
            ? [
                'id' => (int) old('edit_program_id'),
                'name' => old('name', ''),
                'code' => old('code', ''),
                'college_id' => old('college_id') !== null && old('college_id') !== '' ? (string) old('college_id') : '',
                'is_active' => filter_var(old('is_active', '1'), FILTER_VALIDATE_BOOLEAN),
            ]
            : [];

    $collegesForAlpine = $colleges->map(fn ($c) => [
        'code' => $c->code,
        'name' => $c->name,
    ])->values()->all();

    $programsForAlpine = $programs->map(fn ($p) => [
        'code' => $p->code,
        'name' => $p->name,
    ])->values()->all();
@endphp

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .kmsar-switch-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-4);
        padding: 0.625rem 0.875rem;
        border: 1.5px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
    }
    .kmsar-switch {
        position: relative;
        width: 2.75rem;
        height: 1.5rem;
        flex-shrink: 0;
    }
    .kmsar-switch input.sr-only-check {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    .kmsar-switch-track {
        position: absolute;
        inset: 0;
        border-radius: var(--radius-full);
        background: var(--color-border-strong);
        transition: background var(--transition);
        cursor: pointer;
    }
    .kmsar-switch-thumb {
        position: absolute;
        top: 0.1875rem;
        left: 0.1875rem;
        width: 1.125rem;
        height: 1.125rem;
        border-radius: 50%;
        background: var(--color-card);
        box-shadow: var(--shadow-sm);
        transition: transform var(--transition);
    }
    .kmsar-switch input:checked + .kmsar-switch-track {
        background: var(--color-primary);
    }
    .kmsar-switch input:checked + .kmsar-switch-track .kmsar-switch-thumb {
        transform: translateX(1.25rem);
    }
    .kmsar-switch input:focus-visible + .kmsar-switch-track {
        outline: 2px solid var(--color-primary);
        outline-offset: 2px;
    }
</style>
@endpush

@section('content')
<div
    x-data="collegeManager"
    x-on:keydown.escape.window="showEditCollege = false; showEditProgram = false; showAddCollege = false; showAddProgram = false"
>
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:20px 28px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <nav style="font-size:12px;color:#94A3B8;margin-bottom:6px;">
                @if (Route::has('admin.dashboard'))
                    <a href="{{ route('admin.dashboard') }}" style="color:#94A3B8;text-decoration:none;">{{ __('Admin') }}</a>
                @else
                    {{ __('Admin') }}
                @endif
                <span style="margin:0 4px;">/</span>
                {{ __('Colleges & programs') }}
            </nav>
            <h1 style="font-size:22px;font-weight:700;color:#1E3A8A;margin:0 0 4px;">{{ __('Colleges & programs') }}</h1>
            <p style="font-size:13px;color:#475569;margin:0;">{{ __('Manage AUF colleges and their academic programs.') }}</p>
        </div>
    </div>

    @if (session('success'))
        <div class="kmsar-alert kmsar-alert--success kmsar-animate-in mb-5" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="kmsar-stats-grid kmsar-animate-in" style="margin-bottom: var(--space-6);" role="region" aria-label="{{ __('Summary') }}">
        <div class="kmsar-stat-card kmsar-card--accent-primary">
            <div class="kmsar-stat-card-label">{{ __('Colleges') }}</div>
            <div class="kmsar-stat-card-value">{{ number_format($colleges->count()) }}</div>
        </div>
        <div class="kmsar-stat-card kmsar-card--accent-gold">
            <div class="kmsar-stat-card-label">{{ __('Programs') }}</div>
            <div class="kmsar-stat-card-value" style="color: var(--color-gold);">{{ number_format($programs->count()) }}</div>
        </div>
    </div>

    <div id="section-colleges" class="kmsar-card kmsar-card--accent-primary" style="margin-bottom: var(--space-6);">
        <div class="kmsar-card-header">
            <div>
                <h3 class="kmsar-card-title">Colleges</h3>
                <span class="kmsar-hint">
                    {{ $colleges->count() }} colleges
                </span>
            </div>
            <div class="kmsar-page-header-actions">
                <button type="button"
                        class="kmsar-btn kmsar-btn--primary kmsar-btn--sm"
                        @@click="showAddCollege = true">
                    + Add College
                </button>
            </div>
        </div>
        <div class="kmsar-card-body" style="padding-top: 0;">
            <div style="padding:14px 20px 0;">
                <input
                    type="text"
                    x-model="collegeSearch"
                    placeholder="{{ __('Search by college name or code...') }}"
                    autocomplete="off"
                    aria-label="{{ __('Search colleges') }}"
                    style="width:100%;max-width:420px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;text-transform: uppercase"
                >
            </div>
            <div class="kmsar-table-wrap">
                <table class="kmsar-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Code') }}</th>
                            <th scope="col">{{ __('College name') }}</th>
                            <th scope="col">{{ __('Programs') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($colleges as $college)
                            <tr
                                class="border-b border-slate-100 transition-colors"
                                style="{{ $loop->iteration % 2 === 0 ? 'background:#F8FAFC' : 'background:#fff' }}"
                                x-show="collegeRowVisible({{ $loop->index }})"
                            >
                                <td class="px-4 py-3 align-middle text-sm">
                                    <span class="kmsar-ref">{{ $college->code }}</span>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    <div class="kmsar-table-cell-title">{{ $college->name }}</div>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    <x-badge status="gold">{{ number_format($college->programs_count) }}</x-badge>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($college->is_active)
                                        <x-badge status="approved">{{ __('Active') }}</x-badge>
                                    @else
                                        <x-badge status="rejected">{{ __('Inactive') }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap align-middle text-sm">
                                    <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end;">
                                        <button
                                            type="button"
                                            style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;border:1px solid #CBD5E1;color:#475569;border-radius:6px;background:#fff;cursor:pointer;"
                                            aria-label="{{ __('Edit') }}"
                                            x-on:click="openEditCollege({{ $college->id }})"
                                        >{{ __('Edit') }}</button>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.colleges.destroy', $college) }}"
                                            onsubmit="return confirm({{ json_encode(__('Delete :code? This cannot be undone.', ['code' => $college->code])) }})"
                                            style="display:inline;"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="kmsar-btn kmsar-btn--danger-outline kmsar-btn--sm"
                                            >
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                <td colspan="5" class="kmsar-body px-4 py-3 align-middle text-center text-sm" style="color: var(--color-text-muted);">
                                    {{ __('No colleges found.') }}
                                </td>
                            </tr>
                        @endforelse
                        @if ($colleges->isNotEmpty())
                            <tr x-show="visibleCollegeCount === 0" x-cloak class="border-b border-slate-100">
                                <td colspan="5" class="kmsar-body px-4 py-3 align-middle text-center text-sm" style="color: var(--color-text-muted);">
                                    {{ __('No colleges match your search.') }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="section-programs" class="kmsar-card kmsar-card--accent-primary">
        <div class="kmsar-card-header">
            <div>
                <h3 class="kmsar-card-title">Programs</h3>
                <span class="kmsar-hint">
                    {{ $programs->count() }} programs
                </span>
            </div>
            <div class="kmsar-page-header-actions">
                <button type="button"
                        class="kmsar-btn kmsar-btn--primary kmsar-btn--sm"
                        @@click="showAddProgram = true">
                    + Add Program
                </button>
            </div>
        </div>
        <div class="kmsar-card-body" style="padding-top: 0;">
            <div style="padding:14px 20px 0;">
                <input
                    type="text"
                    x-model="programSearch"
                    x-on:input="programPage = 1"
                    placeholder="{{ __('Search by program name or code...') }}"
                    autocomplete="off"
                    aria-label="{{ __('Search programs') }}"
                    style="width:100%;max-width:420px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;text-transform: uppercase"
                >
            </div>
            <div class="kmsar-table-wrap">
                <table class="kmsar-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Code') }}</th>
                            <th scope="col">{{ __('Program name') }}</th>
                            <th scope="col">{{ __('College') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($programs as $program)
                            <tr
                                class="border-b border-slate-100 transition-colors"
                                style="{{ $loop->iteration % 2 === 0 ? 'background:#F8FAFC' : 'background:#fff' }}"
                                x-show="programRowVisible({{ $loop->index }})"
                            >
                                <td class="px-4 py-3 align-middle text-sm">
                                    <span class="kmsar-ref">{{ $program->code }}</span>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    <div class="kmsar-table-cell-title">{{ $program->name }}</div>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($program->college)
                                        <x-badge status="gold">{{ $program->college->code }}</x-badge>
                                    @else
                                        <span class="kmsar-table-cell-sub">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($program->is_active)
                                        <x-badge status="approved">{{ __('Active') }}</x-badge>
                                    @else
                                        <x-badge status="rejected">{{ __('Inactive') }}</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap align-middle text-sm">
                                    <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end;">
                                        <button
                                            type="button"
                                            style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;border:1px solid #CBD5E1;color:#475569;border-radius:6px;background:#fff;cursor:pointer;"
                                            aria-label="{{ __('Edit') }}"
                                            x-on:click="openEditProgram({{ $program->id }})"
                                        >{{ __('Edit') }}</button>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.programs.destroy', $program) }}"
                                            onsubmit="return confirm({{ json_encode(__('Delete :name? This cannot be undone.', ['name' => $program->name])) }})"
                                            style="display:inline;"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="kmsar-btn kmsar-btn--danger-outline kmsar-btn--sm"
                                            >
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                <td colspan="5" class="kmsar-body px-4 py-3 align-middle text-center text-sm" style="color: var(--color-text-muted);">
                                    {{ __('No programs found.') }}
                                </td>
                            </tr>
                        @endforelse
                        @if ($programs->isNotEmpty())
                            <tr x-show="filteredProgramCount === 0" x-cloak class="border-b border-slate-100">
                                <td colspan="5" class="kmsar-body px-4 py-3 align-middle text-center text-sm" style="color: var(--color-text-muted);">
                                    {{ __('No programs match your search.') }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            @if ($programs->isNotEmpty())
                <div x-show="filteredProgramCount > 0" x-cloak style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:14px 20px;border-top:1px solid #E2E8F0;">
                    <span class="kmsar-hint" style="margin:0;" x-text="'{{ __('Showing') }} ' + Math.min(filteredProgramCount, (programPage - 1) * programsPerPage + 1) + '–' + Math.min(programPage * programsPerPage, filteredProgramCount) + ' {{ __('of') }} ' + filteredProgramCount"></span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button
                            type="button"
                            class="kmsar-btn kmsar-btn--outline kmsar-btn--sm"
                            style="min-width:88px;"
                            x-on:click="programPage = Math.max(1, programPage - 1)"
                            x-bind:disabled="programPage <= 1"
                            aria-label="{{ __('Previous page') }}"
                        >{{ __('Prev') }}</button>
                        <span class="kmsar-hint" style="margin:0;min-width:4rem;text-align:center;" x-text="programPage + ' / ' + totalProgramPages"></span>
                        <button
                            type="button"
                            class="kmsar-btn kmsar-btn--outline kmsar-btn--sm"
                            style="min-width:88px;"
                            x-on:click="programPage = Math.min(totalProgramPages, programPage + 1)"
                            x-bind:disabled="programPage >= totalProgramPages"
                            aria-label="{{ __('Next page') }}"
                        >{{ __('Next') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Edit college modal --}}
    <div
        x-show="showEditCollege"
        x-cloak
        class="kmsar-modal-overlay"
        style="display: none;"
        x-on:click.self="showEditCollege = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-edit-college-title"
    >
        <div class="kmsar-modal kmsar-modal--lg" style="max-width:56rem;" x-on:click.stop>
            <div class="kmsar-modal-header">
                <h2 id="modal-edit-college-title" class="kmsar-modal-title">{{ __('Edit college') }}</h2>
                <button type="button" class="kmsar-modal-close" aria-label="{{ __('Close') }}" x-on:click="showEditCollege = false">&times;</button>
            </div>
            <form
                id="form-edit-college"
                x-ref="editCollegeFormEl"
                method="post"
                class="kmsar-modal-body"
                x-bind:action="editCollege && editCollege.id ? `${collegesBase}/${editCollege.id}` : '#'"
                x-on:submit.prevent="$refs.editCollegeFormEl.submit()"
            >
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="_form" value="edit_college">
                <input type="hidden" name="edit_college_id" x-bind:value="editCollege.id">

                @if ($errors->any() && old('_form') === 'edit_college')
                    <div class="kmsar-alert kmsar-alert--danger mb-4" role="alert">
                        <ul class="kmsar-body m-0 list-disc pl-5">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-college-name">{{ __('College name') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="edit-college-name" type="text" name="name" class="kmsar-input @error('name') kmsar-input--error @enderror" required maxlength="150" autocomplete="organization" x-model="editCollege.name" style="text-transform: uppercase">
                            @error('name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-college-code">{{ __('Code') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="edit-college-code" type="text" name="code" class="kmsar-input @error('code') kmsar-input--error @enderror" required maxlength="10" autocomplete="off" x-model="editCollege.code" style="text-transform: uppercase">
                            @error('code')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kmsar-form-group">
                        <span class="kmsar-form-label" id="edit-college-is-active-label">{{ __('Status') }}</span>
                        <div class="kmsar-switch-row" role="group" aria-labelledby="edit-college-is-active-label">
                            <div>
                                <div style="font-size:13px;font-weight:600;color:var(--color-text-primary);">{{ __('Active') }}</div>
                                <div class="kmsar-form-hint" style="margin-top:2px;">{{ __('Inactive colleges may be hidden from selection lists.') }}</div>
                            </div>
                            <label class="kmsar-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="sr-only-check" x-bind:checked="editCollege.is_active" x-on:change="editCollege.is_active = $event.target.checked" aria-label="{{ __('College active') }}">
                                <span class="kmsar-switch-track" aria-hidden="true">
                                    <span class="kmsar-switch-thumb"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
            <div class="kmsar-modal-footer" style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="kmsar-btn kmsar-btn--outline kmsar-btn--md" x-on:click="showEditCollege = false">{{ __('Cancel') }}</button>
                <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--md" form="form-edit-college">{{ __('Save changes') }}</button>
            </div>
        </div>
    </div>

    {{-- Edit program modal --}}
    <div
        x-show="showEditProgram"
        x-cloak
        class="kmsar-modal-overlay"
        style="display: none;"
        x-on:click.self="showEditProgram = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-edit-program-title"
    >
        <div class="kmsar-modal kmsar-modal--lg" style="max-width:56rem;" x-on:click.stop>
            <div class="kmsar-modal-header">
                <h2 id="modal-edit-program-title" class="kmsar-modal-title">{{ __('Edit program') }}</h2>
                <button type="button" class="kmsar-modal-close" aria-label="{{ __('Close') }}" x-on:click="showEditProgram = false">&times;</button>
            </div>
            <form
                id="form-edit-program"
                x-ref="editProgramFormEl"
                method="post"
                class="kmsar-modal-body"
                x-bind:action="editProgram && editProgram.id ? `${programsBase}/${editProgram.id}` : '#'"
                x-on:submit.prevent="$refs.editProgramFormEl.submit()"
            >
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="_form" value="edit_program">
                <input type="hidden" name="edit_program_id" x-bind:value="editProgram.id">

                @if ($errors->any() && old('_form') === 'edit_program')
                    <div class="kmsar-alert kmsar-alert--danger mb-4" role="alert">
                        <ul class="kmsar-body m-0 list-disc pl-5">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-program-name">{{ __('Program name') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="edit-program-name" type="text" name="name" class="kmsar-input @error('name') kmsar-input--error @enderror" required maxlength="200" autocomplete="off" x-model="editProgram.name" style="text-transform: uppercase">
                            @error('name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-program-code">{{ __('Code') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="edit-program-code" type="text" name="code" class="kmsar-input @error('code') kmsar-input--error @enderror" required maxlength="30" autocomplete="off" x-model="editProgram.code" style="text-transform: uppercase">
                            @error('code')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="edit-program-college_id">{{ __('College') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                        <select id="edit-program-college_id" name="college_id" class="kmsar-select @error('college_id') kmsar-input--error @enderror" required x-model="editProgram.college_id">
                            <option value="">{{ __('— Select college —') }}</option>
                            @foreach ($colleges as $c)
                                <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('college_id')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kmsar-form-group">
                        <span class="kmsar-form-label" id="edit-program-is-active-label">{{ __('Status') }}</span>
                        <div class="kmsar-switch-row" role="group" aria-labelledby="edit-program-is-active-label">
                            <div>
                                <div style="font-size:13px;font-weight:600;color:var(--color-text-primary);">{{ __('Active') }}</div>
                                <div class="kmsar-form-hint" style="margin-top:2px;">{{ __('Inactive programs may be hidden from selection lists.') }}</div>
                            </div>
                            <label class="kmsar-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="sr-only-check" x-bind:checked="editProgram.is_active" x-on:change="editProgram.is_active = $event.target.checked" aria-label="{{ __('Program active') }}">
                                <span class="kmsar-switch-track" aria-hidden="true">
                                    <span class="kmsar-switch-thumb"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
            <div class="kmsar-modal-footer" style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="kmsar-btn kmsar-btn--outline kmsar-btn--md" x-on:click="showEditProgram = false">{{ __('Cancel') }}</button>
                <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--md" form="form-edit-program">{{ __('Save changes') }}</button>
            </div>
        </div>
    </div>

    {{-- Add college / program modals: inside x-data scope; teleported to @stack('modals') --}}
    <template x-teleport="#kmsar-modals-root">
        <div
            x-show="showAddCollege"
            x-cloak
            class="kmsar-modal-overlay"
            x-on:click.self="showAddCollege = false"
            style="display: none;"
        >
            <div class="kmsar-modal kmsar-modal--sm">
                <div class="kmsar-modal-header">
                    <h3 class="kmsar-modal-title">{{ __('Add College') }}</h3>
                    <button type="button" class="kmsar-modal-close" x-on:click="showAddCollege = false">&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.colleges.store') }}">
                    @csrf
                    <div class="kmsar-modal-body">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label">
                                {{ __('College Code') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                type="text"
                                name="code"
                                class="kmsar-input"
                                placeholder="{{ __('e.g. CCS') }}"
                                maxlength="10"
                                required
                            >
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label">
                                {{ __('College Name') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                class="kmsar-input"
                                placeholder="{{ __('e.g. College of Computer Studies') }}"
                                required
                            >
                        </div>
                    </div>
                    <div class="kmsar-modal-footer">
                        <button type="button" class="kmsar-btn kmsar-btn--secondary" x-on:click="showAddCollege = false">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="kmsar-btn kmsar-btn--primary">
                            {{ __('Add College') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

    <template x-teleport="#kmsar-modals-root">
        <div
            x-show="showAddProgram"
            x-cloak
            class="kmsar-modal-overlay"
            x-on:click.self="showAddProgram = false"
            style="display: none;"
        >
            <div class="kmsar-modal kmsar-modal--sm">
                <div class="kmsar-modal-header">
                    <h3 class="kmsar-modal-title">{{ __('Add Program') }}</h3>
                    <button type="button" class="kmsar-modal-close" x-on:click="showAddProgram = false">&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.programs.store') }}">
                    @csrf
                    <div class="kmsar-modal-body">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label">
                                {{ __('College') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <select name="college_id" class="kmsar-select" required>
                                <option value="">{{ __('— Select college —') }}</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->id }}">
                                        {{ $college->code }} — {{ $college->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label">
                                {{ __('Program Code') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                type="text"
                                name="code"
                                class="kmsar-input"
                                placeholder="{{ __('e.g. BSIT') }}"
                                maxlength="30"
                                required
                            >
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label">
                                {{ __('Program Name') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                class="kmsar-input"
                                placeholder="{{ __('e.g. Bachelor of Science in Information Technology') }}"
                                required
                            >
                        </div>
                    </div>
                    <div class="kmsar-modal-footer">
                        <button type="button" class="kmsar-btn kmsar-btn--secondary" x-on:click="showAddProgram = false">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="kmsar-btn kmsar-btn--primary">
                            {{ __('Add Program') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
@endsection

@push('modals')
    <div id="kmsar-modals-root"></div>
@endpush

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('collegeManager', () => ({
        showEditCollege: @json($errors->any() && old('_form') === 'edit_college'),
        showEditProgram: @json($errors->any() && old('_form') === 'edit_program'),
        showAddCollege: false,
        showAddProgram: false,
        editCollege: @json($editCollegeInitial ?: new \stdClass()),
        editProgram: @json($editProgramInitial ?: new \stdClass()),
        collegesBase: @json(rtrim(url('/admin/colleges'), '/')),
        programsBase: @json(rtrim(url('/admin/programs'), '/')),
        colleges: @json($collegesForAlpine),
        programs: @json($programsForAlpine),
        collegeSearch: '',
        programSearch: '',
        programPage: 1,
        programsPerPage: 15,
        get filteredProgramIndices() {
            const q = (this.programSearch || '').trim().toLowerCase();
            const list = this.programs || [];
            return list
                .map((p, i) => {
                    if (!p) {
                        return -1;
                    }
                    if (!q) {
                        return i;
                    }
                    const ok =
                        (p.code || '').toLowerCase().includes(q) ||
                        (p.name || '').toLowerCase().includes(q);
                    return ok ? i : -1;
                })
                .filter((i) => i >= 0);
        },
        get filteredProgramCount() {
            return this.filteredProgramIndices.length;
        },
        get totalProgramPages() {
            return Math.max(1, Math.ceil(this.filteredProgramCount / this.programsPerPage));
        },
        programRowVisible(index) {
            const pos = this.filteredProgramIndices.indexOf(index);
            if (pos === -1) {
                return false;
            }
            const start = (this.programPage - 1) * this.programsPerPage;
            return pos >= start && pos < start + this.programsPerPage;
        },
        collegeRowVisible(index) {
            const c = this.colleges[index];
            if (!c) {
                return false;
            }
            const q = (this.collegeSearch || '').trim().toLowerCase();
            if (!q) {
                return true;
            }
            return (
                (c.code || '').toLowerCase().includes(q) ||
                (c.name || '').toLowerCase().includes(q)
            );
        },
        get visibleCollegeCount() {
            if (!this.colleges?.length) {
                return 0;
            }
            let n = 0;
            for (let i = 0; i < this.colleges.length; i += 1) {
                if (this.collegeRowVisible(i)) {
                    n += 1;
                }
            }
            return n;
        },
        init() {
            this.$watch('totalProgramPages', (value) => {
                if (this.programPage > value) {
                    this.programPage = value;
                }
            });
        },
        openEditCollege(collegeId) {
            this.showEditProgram = false;
            this.showAddCollege = false;
            this.showAddProgram = false;
            fetch(`${this.collegesBase}/${collegeId}/edit`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            })
                .then((r) => {
                    if (!r.ok) throw new Error('Failed to load college');
                    return r.json();
                })
                .then((data) => {
                    this.editCollege = {
                        id: data.id,
                        name: data.name ?? '',
                        code: data.code ?? '',
                        is_active: !!data.is_active,
                    };
                    this.showEditCollege = true;
                })
                .catch(() => {});
        },
        openEditProgram(programId) {
            this.showEditCollege = false;
            this.showAddCollege = false;
            this.showAddProgram = false;
            fetch(`${this.programsBase}/${programId}/edit`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            })
                .then((r) => {
                    if (!r.ok) throw new Error('Failed to load program');
                    return r.json();
                })
                .then((data) => {
                    this.editProgram = {
                        id: data.id,
                        name: data.name ?? '',
                        code: data.code ?? '',
                        college_id: data.college_id != null ? String(data.college_id) : '',
                        is_active: !!data.is_active,
                    };
                    this.showEditProgram = true;
                })
                .catch(() => {});
        },
    }));
});
</script>
@endpush

@extends('layouts.app')

@section('title', __('User management — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Admin') }}
@endsection

@php
    $roleBadgeStatus = static function (?string $roleName): string {
        return match ($roleName) {
            'super_admin' => 'solid-primary',
            'ovpri_admin', 'cdaic_admin' => 'info',
            'college_dean', 'unit_head' => 'pending',
            'faculty' => 'approved',
            'co_author' => 'info',
            'registrar' => 'gold',
            'viewer' => 'draft',
            default => 'draft',
        };
    };

    $editUserInitial =
        $errors->any() && old('_form') === 'edit'
            ? [
                'id' => (int) old('edit_user_id'),
                'employee_number' => old('employee_number', ''),
                'first_name' => old('first_name', ''),
                'last_name' => old('last_name', ''),
                'middle_name' => old('middle_name', ''),
                'suffix' => old('suffix', ''),
                'email' => old('email', ''),
                'college_id' => old('college_id') !== null && old('college_id') !== '' ? (string) old('college_id') : '',
                'role' => old('role', ''),
                'is_active' => filter_var(old('is_active', '1'), FILTER_VALIDATE_BOOLEAN),
            ]
            : [];

    $usersForAlpine = $users->map(function ($user) {
        $primaryRole = $user->roles->first();

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_number' => $user->employee_number,
            'role' => $primaryRole?->name ?? '',
            'college_id' => $user->college_id,
            'is_active' => (bool) $user->is_active,
        ];
    })->values()->all();
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
    x-data="userManager"
    x-on:keydown.escape.window="showAdd = false; showEdit = false"
>
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:20px 28px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <nav style="font-size:12px;color:#94A3B8;margin-bottom:6px;">
                @if (Route::has('admin.dashboard'))
                    <a href="{{ route('admin.dashboard') }}" style="color:#94A3B8;text-decoration:none;">{{ __('Admin') }}</a>
                @else
                    {{ __('Admin') }}
                @endif
                <span style="margin:0 4px;">/</span>
                {{ __('User management') }}
            </nav>
            <h1 style="font-size:22px;font-weight:700;color:#1E3A8A;margin:0 0 4px;">{{ __('User management') }}</h1>
            <p style="font-size:13px;color:#475569;margin:0;">{{ __('Create accounts, assign KMSAR roles, and link users to AUF colleges.') }}</p>
        </div>
        <div>
            <button
                type="button"
                class="kmsar-btn kmsar-btn--primary kmsar-btn--md"
                x-on:click="showAdd = true"
                aria-haspopup="dialog"
                x-bind:aria-expanded="showAdd"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-4 w-4 mr-1.5 inline" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('Add user') }}
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="kmsar-alert kmsar-alert--success kmsar-animate-in mb-5" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;" role="search" aria-label="{{ __('Filter users') }}">
        <input
            type="text"
            placeholder="{{ __('Search name, email, employee no...') }}"
            x-model="search"
            autocomplete="off"
            aria-label="{{ __('Search users') }}"
            style="flex:2;min-width:220px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;text-transform: uppercase"
        >
        <select x-model="filterRole" aria-label="{{ __('Filter by role') }}" style="flex:1;min-width:140px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;background:#fff;">
            <option value="">{{ __('All Roles') }}</option>
            @foreach ($kmsarRoles as $slug => $label)
                <option value="{{ $slug }}">{{ $label }}</option>
            @endforeach
        </select>
        <select x-model="filterCollege" aria-label="{{ __('Filter by college') }}" style="flex:1;min-width:160px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;background:#fff;">
            <option value="">{{ __('All Colleges') }}</option>
            @foreach ($colleges as $college)
                <option value="{{ (string) $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
            @endforeach
        </select>
        <select x-model="filterStatus" aria-label="{{ __('Filter by status') }}" style="min-width:120px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;background:#fff;">
            <option value="">{{ __('All Status') }}</option>
            <option value="1">{{ __('Active') }}</option>
            <option value="0">{{ __('Inactive') }}</option>
        </select>
        <span style="font-size:12px;color:#94A3B8;white-space:nowrap;" x-text="'{{ __('Showing') }} ' + visibleCount + ' {{ __('of') }} {{ $users->count() }} {{ __('users') }}'"></span>
    </div>

    <div class="kmsar-card kmsar-card--accent-primary">
        <div class="kmsar-card-header">
            <div>
                <h2 class="kmsar-card-title">{{ __('Directory') }}</h2>
                <span class="kmsar-hint mt-1 block">{{ $users->count() }} {{ \Illuminate\Support\Str::plural(__('user'), $users->count()) }}</span>
            </div>
        </div>
        <div class="kmsar-card-body" style="padding-top: 0;">
            <div class="kmsar-table-wrap">
                <table class="kmsar-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Employee No.') }}</th>
                            <th scope="col">{{ __('Name') }}</th>
                            <th scope="col">{{ __('Role') }}</th>
                            <th scope="col">{{ __('College') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Last login') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $primaryRole = $user->roles->first();
                                $roleSlug = $primaryRole?->name;
                                $roleLabel = $roleSlug ? ($kmsarRoles[$roleSlug] ?? str_replace('_', ' ', $roleSlug)) : '—';
                                $filterUser = $usersForAlpine[$loop->index] ?? [];
                            @endphp
                            <tr
                                class="border-b border-slate-100 hover:bg-slate-50 transition-colors"
                                x-show="rowVisible(users[{{ $loop->index }}])"
                                data-user="{{ e(json_encode($filterUser)) }}"
                                x-bind:data-name="users[{{ $loop->index }}]?.name ?? ''"
                                x-bind:data-email="users[{{ $loop->index }}]?.email ?? ''"
                                x-bind:data-role="users[{{ $loop->index }}]?.role ?? ''"
                                x-bind:data-college="users[{{ $loop->index }}]?.college_id != null ? String(users[{{ $loop->index }}].college_id) : ''"
                                x-bind:data-status="(users[{{ $loop->index }}]?.is_active) ? 'active' : 'inactive'"
                            >
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($user->employee_number)
                                        <span class="kmsar-ref">{{ $user->employee_number }}</span>
                                    @else
                                        <span class="kmsar-table-cell-sub">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    <div class="kmsar-table-cell-title">{{ $user->name }}</div>
                                    <div class="kmsar-table-cell-sub">{{ $user->email }}</div>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($primaryRole)
                                        <x-badge :status="$roleBadgeStatus($roleSlug)">{{ $roleLabel }}</x-badge>
                                    @else
                                        <span class="kmsar-table-cell-sub">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($user->college)
                                        <span class="kmsar-table-cell-title">{{ $user->college->code }}</span>
                                        <span class="kmsar-table-cell-sub block">{{ $user->college->name }}</span>
                                    @else
                                        <span class="kmsar-table-cell-sub">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($user->is_active)
                                        <x-badge status="approved">{{ __('Active') }}</x-badge>
                                    @else
                                        <x-badge status="rejected">{{ __('Inactive') }}</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap kmsar-table-cell-sub px-4 py-3 align-middle text-sm">
                                    {{ $user->last_login_at?->format('M j, Y g:i a') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap align-middle text-sm">
                                    <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end;">
                                        <button
                                            type="button"
                                            style="display:inline-flex;align-items:center;padding:4px 12px;font-size:12px;font-weight:500;border:1px solid #CBD5E1;color:#475569;border-radius:6px;background:#fff;cursor:pointer;"
                                            aria-label="{{ __('Edit') }}"
                                            x-on:click="openEdit({{ $user->id }})"
                                        >{{ __('Edit') }}</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                <td colspan="7" class="kmsar-body px-4 py-3 align-middle text-center text-sm" style="color: var(--color-text-muted);">
                                    {{ __('No users found.') }}
                                </td>
                            </tr>
                        @endforelse
                        @if ($users->isNotEmpty())
                            <tr x-show="visibleCount === 0" x-cloak class="border-b border-slate-100">
                                <td colspan="7" class="kmsar-body px-4 py-3 align-middle text-center text-sm" style="color: var(--color-text-muted);">
                                    {{ __('No users match your filters.') }}
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Add user modal --}}
    <div
        x-show="showAdd"
        x-cloak
        class="kmsar-modal-overlay"
        style="display: none;"
        x-on:click.self="showAdd = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-add-user-title"
    >
        <div class="kmsar-modal kmsar-modal--lg" style="max-width:56rem; max-height: none; overflow-y: visible;" x-on:click.stop>
            <div class="kmsar-modal-header">
                <h2 id="modal-add-user-title" class="kmsar-modal-title">{{ __('Add user') }}</h2>
                <button type="button" class="kmsar-modal-close" aria-label="{{ __('Close') }}" x-on:click="showAdd = false">&times;</button>
            </div>
            <form id="form-add-user" method="post" action="{{ route('admin.users.store') }}" class="kmsar-modal-body">
                @csrf
                <input type="hidden" name="_form" value="add">

                @if ($errors->any() && old('_form', 'add') === 'add')
                    <div class="kmsar-alert kmsar-alert--danger mb-4" role="alert">
                        <ul class="kmsar-body m-0 list-disc pl-5">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="kmsar-form-row-3">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-first_name">
                                {{ __('First Name') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                id="add-first_name"
                                type="text"
                                name="first_name"
                                class="kmsar-input @error('first_name') kmsar-input--error @enderror"
                                value="{{ old('first_name') }}"
                                required
                            >
                            @error('first_name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-last_name">
                                {{ __('Last Name') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                id="add-last_name"
                                type="text"
                                name="last_name"
                                class="kmsar-input @error('last_name') kmsar-input--error @enderror"
                                value="{{ old('last_name') }}"
                                required
                            >
                            @error('last_name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-middle_name">{{ __('Middle Name') }}</label>
                            <input
                                id="add-middle_name"
                                type="text"
                                name="middle_name"
                                class="kmsar-input @error('middle_name') kmsar-input--error @enderror"
                                value="{{ old('middle_name') }}"
                            >
                            @error('middle_name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kmsar-form-row-2">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-suffix">{{ __('Suffix') }}</label>
                            <input
                                id="add-suffix"
                                type="text"
                                name="suffix"
                                class="kmsar-input @error('suffix') kmsar-input--error @enderror"
                                value="{{ old('suffix') }}"
                                placeholder="{{ __('Jr., Sr., III, etc.') }}"
                                style="text-transform: none;"
                            >
                            @error('suffix')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-employee_number">{{ __('Employee number') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="add-employee_number" type="text" name="employee_number" class="kmsar-input @error('employee_number') kmsar-input--error @enderror" value="{{ old('employee_number') }}" required autocomplete="off" placeholder="{{ __('e.g. AUF-2024-0001') }}" style="text-transform: uppercase">
                            @error('employee_number')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="add-email">{{ __('Email') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                        <input id="add-email" type="email" name="email" class="kmsar-input @error('email') kmsar-input--error @enderror" value="{{ old('email') }}" required autocomplete="email" style="text-transform: uppercase">
                        @error('email')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kmsar-form-row-2">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-password">{{ __('Password') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="add-password" type="password" name="password" class="kmsar-input @error('password') kmsar-input--error @enderror" required autocomplete="new-password" minlength="8">
                            @error('password')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-password_confirmation">{{ __('Confirm password') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="add-password_confirmation" type="password" name="password_confirmation" class="kmsar-input" required autocomplete="new-password" minlength="8">
                        </div>
                    </div>

                    <div class="kmsar-form-row-3">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-college_id">{{ __('College') }}</label>
                            <select id="add-college_id" name="college_id" class="kmsar-select">
                                <option value="">{{ __('— Select college —') }}</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->id }}" @selected((string) old('college_id') === (string) $college->id)>{{ $college->code }} — {{ $college->name }}</option>
                                @endforeach
                            </select>
                            @error('college_id')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="add-role">{{ __('Role') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <select id="add-role" name="role" class="kmsar-select @error('role') kmsar-input--error @enderror" required>
                                <option value="">{{ __('— Select role —') }}</option>
                                @foreach ($kmsarRoles as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('role') === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="kmsar-form-group">
                            <span class="kmsar-form-label" id="add-is-active-label">{{ __('Account status') }}</span>
                            <div class="kmsar-switch-row" role="group" aria-labelledby="add-is-active-label">
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--color-text-primary);">{{ __('Active') }}</div>
                                    <div class="kmsar-form-hint" style="margin-top:2px;">{{ __('Inactive users cannot sign in.') }}</div>
                                </div>
                                <label class="kmsar-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only-check" @checked(old('is_active', '1') === '1' || old('is_active', '1') === true) aria-label="{{ __('User account active') }}">
                                    <span class="kmsar-switch-track" aria-hidden="true">
                                        <span class="kmsar-switch-thumb"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="kmsar-modal-footer" style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="kmsar-btn kmsar-btn--outline kmsar-btn--md" x-on:click="showAdd = false">{{ __('Cancel') }}</button>
                <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--md" form="form-add-user">{{ __('Create user') }}</button>
            </div>
        </div>
    </div>

    {{-- Edit user modal --}}
    <div
        x-show="showEdit"
        x-cloak
        class="kmsar-modal-overlay"
        style="display: none;"
        x-on:click.self="showEdit = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-edit-user-title"
    >
        <div class="kmsar-modal kmsar-modal--lg" style="max-width:56rem; max-height: none; overflow-y: visible;" x-on:click.stop>
            <div class="kmsar-modal-header">
                <h2 id="modal-edit-user-title" class="kmsar-modal-title">{{ __('Edit user') }}</h2>
                <button type="button" class="kmsar-modal-close" aria-label="{{ __('Close') }}" x-on:click="showEdit = false">&times;</button>
            </div>
            <form
                id="form-edit-user"
                x-ref="editFormEl"
                method="post"
                class="kmsar-modal-body"
                style="overflow-y: auto; max-height: 80vh;"
                x-bind:action="editUser && editUser.id ? `${adminUsersBase}/${editUser.id}` : '#'"
                x-on:submit.prevent="$refs.editFormEl.submit()"
            >
                @csrf
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="_form" value="edit">
                <input type="hidden" name="edit_user_id" x-bind:value="editUser.id">

                @if ($errors->any() && old('_form') === 'edit')
                    <div class="kmsar-alert kmsar-alert--danger mb-4" role="alert">
                        <ul class="kmsar-body m-0 list-disc pl-5">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="kmsar-form-row-3">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-first_name">
                                {{ __('First Name') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                id="edit-first_name"
                                type="text"
                                name="first_name"
                                class="kmsar-input @error('first_name') kmsar-input--error @enderror"
                                x-model="editUser.first_name"
                                required
                            >
                            @error('first_name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-last_name">
                                {{ __('Last Name') }}
                                <span class="kmsar-form-required">*</span>
                            </label>
                            <input
                                id="edit-last_name"
                                type="text"
                                name="last_name"
                                class="kmsar-input @error('last_name') kmsar-input--error @enderror"
                                x-model="editUser.last_name"
                                required
                            >
                            @error('last_name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-middle_name">{{ __('Middle Name') }}</label>
                            <input
                                id="edit-middle_name"
                                type="text"
                                name="middle_name"
                                class="kmsar-input @error('middle_name') kmsar-input--error @enderror"
                                x-model="editUser.middle_name"
                            >
                            @error('middle_name')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kmsar-form-row-2">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-suffix">{{ __('Suffix') }}</label>
                            <input
                                id="edit-suffix"
                                type="text"
                                name="suffix"
                                class="kmsar-input @error('suffix') kmsar-input--error @enderror"
                                placeholder="{{ __('Jr., Sr., III, etc.') }}"
                                style="text-transform: none;"
                                x-model="editUser.suffix"
                            >
                            @error('suffix')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-employee_number">{{ __('Employee number') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <input id="edit-employee_number" type="text" name="employee_number" class="kmsar-input @error('employee_number') kmsar-input--error @enderror" required autocomplete="off" x-model="editUser.employee_number" style="text-transform: uppercase">
                            @error('employee_number')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="kmsar-form-group">
                        <label class="kmsar-form-label" for="edit-email">{{ __('Email') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                        <input id="edit-email" type="email" name="email" class="kmsar-input @error('email') kmsar-input--error @enderror" required autocomplete="email" x-model="editUser.email" style="text-transform: uppercase">
                        @error('email')
                            <p class="kmsar-form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="kmsar-form-row-2">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-password">{{ __('New password') }}</label>
                            <input id="edit-password" type="password" name="password" class="kmsar-input @error('password') kmsar-input--error @enderror" autocomplete="new-password" minlength="8" placeholder="{{ __('Leave blank to keep current') }}">
                            @error('password')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-password_confirmation">{{ __('Confirm new password') }}</label>
                            <input id="edit-password_confirmation" type="password" name="password_confirmation" class="kmsar-input" autocomplete="new-password" minlength="8">
                        </div>
                    </div>

                    <div class="kmsar-form-row-3">
                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-college_id">{{ __('College') }}</label>
                            <select id="edit-college_id" name="college_id" class="kmsar-select" x-model="editUser.college_id">
                                <option value="">{{ __('— Select college —') }}</option>
                                @foreach ($colleges as $college)
                                    <option value="{{ $college->id }}">{{ $college->code }} — {{ $college->name }}</option>
                                @endforeach
                            </select>
                            @error('college_id')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="kmsar-form-group">
                            <label class="kmsar-form-label" for="edit-role">{{ __('Role') }} <span class="kmsar-form-required" aria-hidden="true">*</span></label>
                            <select id="edit-role" name="role" class="kmsar-select @error('role') kmsar-input--error @enderror" required x-model="editUser.role">
                                <option value="">{{ __('— Select role —') }}</option>
                                @foreach ($kmsarRoles as $slug => $label)
                                    <option value="{{ $slug }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <p class="kmsar-form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="kmsar-form-group">
                            <span class="kmsar-form-label" id="edit-is-active-label">{{ __('Account status') }}</span>
                            <div class="kmsar-switch-row" role="group" aria-labelledby="edit-is-active-label">
                                <div>
                                    <div style="font-size:13px;font-weight:600;color:var(--color-text-primary);">{{ __('Active') }}</div>
                                    <div class="kmsar-form-hint" style="margin-top:2px;">{{ __('Inactive users cannot sign in.') }}</div>
                                </div>
                                <label class="kmsar-switch">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only-check" x-bind:checked="editUser.is_active" x-on:change="editUser.is_active = $event.target.checked" aria-label="{{ __('User account active') }}">
                                    <span class="kmsar-switch-track" aria-hidden="true">
                                        <span class="kmsar-switch-thumb"></span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <div class="kmsar-modal-footer" style="display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="kmsar-btn kmsar-btn--outline kmsar-btn--md" x-on:click="showEdit = false">{{ __('Cancel') }}</button>
                <button type="submit" class="kmsar-btn kmsar-btn--primary kmsar-btn--md" form="form-edit-user">{{ __('Save changes') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('userManager', () => ({
        showAdd: @json($errors->any() && old('_form', 'add') === 'add'),
        showEdit: @json($errors->any() && old('_form') === 'edit'),
        editUser: @json($editUserInitial ?: new \stdClass()),
        adminUsersBase: @json(rtrim(url('/admin/users'), '/')),
        users: @json($usersForAlpine),
        search: '',
        filterRole: '',
        filterCollege: '',
        filterStatus: '',
        get visibleCount() {
            if (!this.users || !this.users.length) {
                return 0;
            }
            return this.users.filter((u) => this.rowVisible(u)).length;
        },
        rowVisible(u) {
            if (!u) {
                return false;
            }
            const q = (this.search || '').trim().toLowerCase();
            if (q) {
                const name = (u.name || '').toLowerCase();
                const email = (u.email || '').toLowerCase();
                const emp = (u.employee_number || '').toLowerCase();
                if (!name.includes(q) && !email.includes(q) && !emp.includes(q)) {
                    return false;
                }
            }
            if (this.filterRole && (u.role || '') !== this.filterRole) {
                return false;
            }
            if (this.filterCollege !== '' && this.filterCollege != null) {
                const cid = u.college_id != null ? String(u.college_id) : '';
                if (cid !== String(this.filterCollege)) {
                    return false;
                }
            }
            if (this.filterStatus === '1' && !u.is_active) {
                return false;
            }
            if (this.filterStatus === '0' && u.is_active) {
                return false;
            }
            return true;
        },
        openEdit(userId) {
            fetch(`${this.adminUsersBase}/${userId}/edit`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            })
                .then((r) => {
                    if (!r.ok) throw new Error('Failed to load user');
                    return r.json();
                })
                .then((data) => {
                    this.editUser = {
                        id: data.id,
                        employee_number: data.employee_number ?? '',
                        first_name: data.first_name ?? '',
                        last_name: data.last_name ?? '',
                        middle_name: data.middle_name ?? '',
                        suffix: data.suffix ?? '',
                        email: data.email ?? '',
                        college_id: data.college_id != null ? String(data.college_id) : '',
                        role: data.role ?? '',
                        is_active: !!data.is_active,
                    };
                    this.showEdit = true;
                })
                .catch(() => {});
        },
    }));
});
</script>
@endpush

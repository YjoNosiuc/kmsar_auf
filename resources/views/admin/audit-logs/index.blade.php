@extends('layouts.app')

@section('title', __('Audit logs — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Audit logs') }}
@endsection

@section('content')
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:20px 28px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <nav style="font-size:12px;color:#94A3B8;margin-bottom:6px;">
                @if (Route::has('admin.dashboard'))
                    <a href="{{ route('admin.dashboard') }}" style="color:#94A3B8;text-decoration:none;">{{ __('Admin') }}</a>
                @else
                    {{ __('Admin') }}
                @endif
                <span style="margin:0 4px;">/</span>
                {{ __('Audit logs') }}
            </nav>
            <h1 style="font-size:22px;font-weight:700;color:#1E3A8A;margin:0 0 4px;">{{ __('Audit logs') }}</h1>
            <p style="font-size:13px;color:#475569;margin:0;">{{ __('Immutable record of significant actions with actor, target, and request metadata.') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('audit.index') }}" style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <input type="text" name="user" placeholder="{{ __('Search user...') }}" value="{{ request('user') }}"
            style="flex:1;min-width:180px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;text-transform: uppercase"
            autocomplete="off"
            aria-label="{{ __('Search user') }}"
        >
        <select name="action" style="min-width:160px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;background:#fff;font-family:inherit;" aria-label="{{ __('Action') }}">
            <option value="">{{ __('All Actions') }}</option>
            @foreach ($filterActions as $a)
                <option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <select name="record_type" style="min-width:140px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;background:#fff;font-family:inherit;" aria-label="{{ __('Record type') }}">
            <option value="">{{ __('All Types') }}</option>
            @foreach ($filterTypes as $t)
                <option value="{{ $t }}" @selected(request('record_type') === $t || request('auditable_type') === $t)>{{ class_basename($t) }}</option>
            @endforeach
        </select>
        <input name="date_from" type="date" value="{{ request('date_from') }}"
            style="min-width:140px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;"
            aria-label="{{ __('From date') }}"
        >
        <input name="date_to" type="date" value="{{ request('date_to') }}"
            style="min-width:140px;padding:8px 12px;border:1px solid #E2E8F0;border-radius:6px;font-size:13px;font-family:inherit;"
            aria-label="{{ __('To date') }}"
        >
        <button type="submit" style="padding:8px 18px;background:#1E3A8A;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">{{ __('Filter') }}</button>
        <a href="{{ route('audit.index') }}" style="padding:8px 14px;border:1px solid #E2E8F0;color:#475569;border-radius:6px;font-size:13px;text-decoration:none;">{{ __('Reset') }}</a>
    </form>

    <div class="kmsar-card kmsar-card--accent-gold">
        <div class="kmsar-card-header">
            <div>
                <h2 class="kmsar-card-title">{{ __('Activity') }}</h2>
                @if ($logs->total() > 0)
                    <span class="kmsar-hint mt-1 block">{{ number_format($logs->total()) }} {{ \Illuminate\Support\Str::plural(__('entry'), $logs->total()) }}</span>
                @endif
            </div>
        </div>
        <div class="kmsar-card-body" style="padding-top: 0;">
            <div class="kmsar-table-wrap">
                <table class="kmsar-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('User') }}</th>
                            <th scope="col">{{ __('Action') }}</th>
                            <th scope="col">{{ __('Record') }}</th>
                            <th scope="col">{{ __('Timestamp') }}</th>
                            <th scope="col">{{ __('IP') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b border-slate-100 transition-colors" style="{{ $loop->iteration % 2 === 0 ? 'background:#F8FAFC' : 'background:#fff' }}">
                                <td class="px-4 py-3 align-middle text-sm">
                                    @if ($log->user)
                                        <div class="kmsar-table-cell-title">{{ $log->user->name }}</div>
                                        <div class="kmsar-table-cell-sub">{{ $log->user->email }}</div>
                                    @else
                                        <span class="kmsar-table-cell-sub">{{ __('System') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-sm"><span class="kmsar-badge kmsar-badge--solid-primary kmsar-badge--square">{{ $log->action }}</span></td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    <code class="kmsar-ref" style="font-size: var(--text-2xs); word-break: break-all;">{{ class_basename($log->auditable_type) }}</code>
                                    <div class="kmsar-table-cell-sub">#{{ $log->auditable_id }}</div>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm">
                                    <div class="kmsar-table-cell-title">{{ $log->created_at->format('M j, Y') }}</div>
                                    <div class="kmsar-table-cell-sub">{{ $log->created_at->format('g:i A') }}</div>
                                </td>
                                <td class="px-4 py-3 align-middle text-sm"><span class="kmsar-ref">{{ $log->ip_address }}</span></td>
                            </tr>
                        @empty
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                <td colspan="5" class="kmsar-body px-4 py-3 align-middle text-center text-sm" style="color: var(--color-text-muted);">
                                    @if (request()->anyFilled(['user', 'action', 'record_type', 'auditable_type', 'auditable_id', 'ip_address', 'date_from', 'date_to']))
                                        {{ __('No audit log entries match your filters.') }}
                                    @else
                                        {{ __('No audit log entries yet.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div style="margin-top: var(--space-5); display: flex; justify-content: center;">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', __('Import Faculty Users — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Admin') }}
@endsection

@section('content')
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:20px 28px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <nav style="font-size:12px;color:#94A3B8;margin-bottom:6px;">
                @if (Route::has('admin.dashboard'))
                    <a href="{{ route('admin.dashboard') }}" style="color:#94A3B8;text-decoration:none;">{{ __('Admin') }}</a>
                @else
                    {{ __('Admin') }}
                @endif
                <span style="margin:0 4px;">/</span>
                {{ __('Import Faculty Users') }}
            </nav>
            <h1 style="font-size:22px;font-weight:700;color:#1E3A8A;margin:0 0 4px;">{{ __('Import Faculty Users') }}</h1>
            <p style="font-size:13px;color:#475569;margin:0;">{{ __('Upload an .xlsx file to create faculty and staff accounts in bulk.') }}</p>
        </div>
        @if (Route::has('admin.import.research'))
            <a href="{{ route('admin.import.research') }}" class="kmsar-btn kmsar-btn--secondary kmsar-btn--sm">
                {{ __('Go to Research Import') }}
            </a>
        @endif
    </div>

    <div class="kmsar-alert kmsar-alert--info mb-5" role="status">
        <strong>{{ __('Step 1 of 2') }}</strong>
        — {{ __('Import faculty accounts before importing research records.') }}
    </div>

    @if ($errors->any())
        <div class="kmsar-alert kmsar-alert--danger mb-5" role="alert">
            <ul class="list-disc pl-5 m-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $results = session('import_results');
    @endphp

    @if (is_array($results))
        <div class="kmsar-card kmsar-card--accent-gold mb-5">
            <div class="kmsar-card-header">
                <h2 class="kmsar-card-title">{{ __('Import results') }}</h2>
            </div>
            <div class="kmsar-card-body">
                <div class="kmsar-alert kmsar-alert--success mb-4" role="status">
                    {{ (int) ($results['imported'] ?? 0) }} {{ __('users imported successfully') }}
                </div>

                @if (! empty($results['skipped']))
                    <p class="text-sm font-semibold mb-2" style="color:#DC2626;">
                        {{ count($results['skipped']) }} {{ __('row(s) skipped') }}
                    </p>
                    <div class="kmsar-table-wrap">
                        <table class="kmsar-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Row #') }}</th>
                                    <th scope="col">{{ __('Value') }}</th>
                                    <th scope="col">{{ __('Reason') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($results['skipped'] as $skip)
                                    <tr>
                                        <td style="color:#DC2626;">{{ $skip['row'] ?? '—' }}</td>
                                        <td style="color:#DC2626;">{{ $skip['value'] ?? '—' }}</td>
                                        <td style="color:#DC2626;">{{ $skip['reason'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="kmsar-card">
        <div class="kmsar-card-header">
            <div>
                <h2 class="kmsar-card-title">{{ __('Upload Excel file') }}</h2>
                <span class="kmsar-hint mt-1 block">
                    {{ __('Accepted format: .xlsx only. Maximum size: 10 MB. Data starts at row 3 (row 1 = headers, row 2 = instructions).') }}
                </span>
            </div>
        </div>
        <div class="kmsar-card-body">
            <form method="POST" action="{{ route('admin.import.users.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="user-import-file" class="kmsar-label">{{ __('Excel file (.xlsx)') }}</label>
                    <input
                        id="user-import-file"
                        type="file"
                        name="file"
                        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        required
                        class="kmsar-input"
                    >
                    <p class="kmsar-hint mt-2">
                        {{ __('Required columns: name, email, employee_number, college_code, office, role, password') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit" class="kmsar-btn kmsar-btn--primary">
                        {{ __('Import Users') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

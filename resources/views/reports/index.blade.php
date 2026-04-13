@extends('layouts.app')

@section('title', __('Reports & Analytics — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Reports & Analytics') }}
@endsection

@section('content')
    @php
        $filterHidden = collect($filters ?? [])->filter(fn ($v) => $v !== null && $v !== '')->all();
        $statusKeys = [
            'proposal',
            'ongoing',
            'completed_unpublished',
            'presented_internal',
            'presented_external',
            'published_non_indexed',
            'published_scopus',
            'patent_submitted',
            'patent_granted',
        ];
        $classificationKeys = [
            'self_funded',
            'internally_funded',
            'externally_funded',
            'thesis',
            'collaboration',
            'other',
        ];
        $stats = $reportStats ?? (
            $reportScope === 'college'
                ? ['matching' => $totalCount, 'published' => 0, 'presented' => 0]
                : ['matching' => $totalCount, 'scopus' => 0, 'colleges_or_faculty' => 0]
        );
        $exportReportType = $reportScope === 'ovpri' ? 'ovpri' : 'college';
    @endphp

    {{-- 1. Page header --}}
    <div style="margin-bottom:20px;">
        <h1 style="font-size:24px;font-weight:700;color:#1E3A8A;margin:0 0 6px;">{{ __('Reports & Analytics') }}</h1>
        <p style="font-size:13px;color:#475569;margin:0;">{{ $pageSubtitle ?? __('Generate and export filtered institutional research reports') }}</p>
    </div>

    @if (session('success'))
        <div class="kmsar-alert kmsar-alert--success" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="kmsar-alert kmsar-alert--danger" style="margin-bottom:1rem;">
            <ul style="margin:0; padding-left:1.25rem;">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 2. Filter bar card --}}
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:20px 24px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
            <div>
                <span style="font-size:13px;font-weight:600;color:#0F172A;">{{ __('Filter Report') }}</span>
                <span style="font-size:12px;color:#94A3B8;margin-left:8px;">{{ __('Leave blank to include all records') }}</span>
            </div>
            <a href="{{ route('reports.index') }}" style="font-size:12px;color:#94A3B8;text-decoration:none;">{{ __('Reset filters') }}</a>
        </div>
        <form method="GET" action="{{ route('reports.index') }}">
            <div class="kmsar-reports-filter-row" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 160px 160px auto;gap:12px;align-items:flex-end;">
                @if ($reportScope === 'ovpri')
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="college_id">{{ __('College') }}</label>
                        <select id="college_id" name="college_id" class="kmsar-select" style="width:100%;">
                            <option value="">{{ __('All Colleges') }}</option>
                            @foreach ($colleges as $c)
                                <option value="{{ $c->id }}" @selected(old('college_id', $filters['college_id'] ?? '') == (string) $c->id)>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="faculty">{{ __('Faculty') }}</label>
                        <select id="faculty" name="faculty" class="kmsar-select" style="width:100%;">
                            <option value="">{{ __('All Faculty') }}</option>
                            @foreach ($faculties as $f)
                                <option value="{{ $f->id }}" @selected(old('faculty', $filters['faculty'] ?? '') == (string) $f->id)>
                                    {{ $f->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="{{ $reportScope === 'ovpri' ? 'research_classification' : 'research_classification_dean' }}">{{ __('Classification') }}</label>
                    <select id="{{ $reportScope === 'ovpri' ? 'research_classification' : 'research_classification_dean' }}" name="research_classification" class="kmsar-select" style="width:100%;">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($classificationKeys as $ck)
                            <option value="{{ $ck }}" @selected(old('research_classification', $filters['research_classification'] ?? '') === $ck)>
                                {{ $reportGenerator->classificationLabel($ck) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="{{ $reportScope === 'ovpri' ? 'status' : 'status_dean' }}">{{ $reportScope === 'ovpri' ? __('Status') : __('Progress Status') }}</label>
                    <select id="{{ $reportScope === 'ovpri' ? 'status' : 'status_dean' }}" name="status" class="kmsar-select" style="width:100%;">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($statusKeys as $sk)
                            <option value="{{ $sk }}" @selected(old('status', $filters['status'] ?? '') === $sk)>
                                {{ $reportGenerator->statusLabel($sk) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="{{ $reportScope === 'ovpri' ? 'date_from' : 'date_from_dean' }}">{{ __('Date From') }}</label>
                    <input
                        id="{{ $reportScope === 'ovpri' ? 'date_from' : 'date_from_dean' }}"
                        type="date"
                        name="date_from"
                        class="kmsar-input"
                        style="width:100%;"
                        value="{{ old('date_from', $filters['date_from'] ?? '') }}"
                    >
                </div>
                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="{{ $reportScope === 'ovpri' ? 'date_to' : 'date_to_dean' }}">{{ __('Date To') }}</label>
                    <input
                        id="{{ $reportScope === 'ovpri' ? 'date_to' : 'date_to_dean' }}"
                        type="date"
                        name="date_to"
                        class="kmsar-input"
                        style="width:100%;"
                        value="{{ old('date_to', $filters['date_to'] ?? '') }}"
                    >
                </div>
                <div>
                    <button type="submit" style="padding:9px 20px;background:#1E3A8A;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;">{{ __('Apply') }}</button>
                </div>
            </div>
        </form>
    </div>

    {{-- 3. Stats row --}}
    <div class="kmsar-reports-stats" style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;">
        @if ($reportScope === 'ovpri')
            <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px;border-top:3px solid #1E3A8A;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin-bottom:4px;">{{ __('Total Records') }}</div>
                <div style="font-size:28px;font-weight:700;color:#1E3A8A;">{{ number_format($stats['matching']) }}</div>
            </div>
            <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px;border-top:3px solid #D4AF37;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin-bottom:4px;">{{ __('Scopus indexed') }}</div>
                <div style="font-size:28px;font-weight:700;color:#D4AF37;">{{ number_format($stats['scopus']) }}</div>
            </div>
            <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px;border-top:3px solid #059669;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin-bottom:4px;">{{ __('Colleges represented') }}</div>
                <div style="font-size:28px;font-weight:700;color:#059669;">{{ number_format($stats['colleges_or_faculty']) }}</div>
            </div>
        @else
            <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px;border-top:3px solid #1E3A8A;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin-bottom:4px;">{{ __('Total Research') }}</div>
                <div style="font-size:28px;font-weight:700;color:#1E3A8A;">{{ number_format($stats['matching']) }}</div>
            </div>
            <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px;border-top:3px solid #D4AF37;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin-bottom:4px;">{{ __('Published') }}</div>
                <div style="font-size:28px;font-weight:700;color:#D4AF37;">{{ number_format($stats['published']) }}</div>
            </div>
            <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px;border-top:3px solid #059669;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin-bottom:4px;">{{ __('Presented') }}</div>
                <div style="font-size:28px;font-weight:700;color:#059669;">{{ number_format($stats['presented']) }}</div>
            </div>
        @endif
    </div>

    {{-- 4. Data Preview card --}}
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="font-size:14px;font-weight:600;color:#0F172A;margin:0;">{{ __('Data Preview') }}</h3>
                <p style="font-size:12px;color:#94A3B8;margin:2px 0 0;">
                    @if ($totalCount > 0)
                        {{ __('Showing first :n of :total records', ['n' => min(10, $totalCount), 'total' => number_format($totalCount)]) }}
                    @else
                        {{ __('No records match the current filters.') }}
                    @endif
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <form method="post" action="{{ route('reports.export') }}" style="display:inline;margin:0;">
                    @csrf
                    <input type="hidden" name="report_type" value="{{ $exportReportType }}">
                    <input type="hidden" name="format" value="excel">
                    @foreach ($filterHidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#059669;color:#fff;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        {{ __('Excel') }}
                    </button>
                </form>
                <form method="post" action="{{ route('reports.export') }}" style="display:inline;margin:0;">
                    @csrf
                    <input type="hidden" name="report_type" value="{{ $exportReportType }}">
                    <input type="hidden" name="format" value="pdf">
                    @foreach ($filterHidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#DC2626;color:#fff;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        {{ __('PDF') }}
                    </button>
                </form>
            </div>
        </div>
        <div style="padding:0;">
            <div class="kmsar-table-wrap" style="margin:0;">
                <table class="kmsar-table">
                    <thead>
                        <tr>
                            @if ($reportScope === 'ovpri')
                                <th scope="col">{{ __('Reference') }}</th>
                            @endif
                            <th scope="col">{{ __("Author's Name") }}</th>
                            @if ($reportScope === 'ovpri')
                                <th scope="col">{{ __('College') }}</th>
                            @endif
                            <th scope="col">{{ __('Co-Authors') }}</th>
                            @if ($reportScope === 'ovpri')
                                <th scope="col">{{ __('Title') }}</th>
                                <th scope="col">{{ __('Classification') }}</th>
                                <th scope="col">{{ __('Status / Journal') }}</th>
                            @else
                                <th scope="col">{{ __('Title of Research') }}</th>
                                <th scope="col">{{ __('Journal/Conference Presentation') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($preview as $row)
                            <tr>
                                @if ($reportScope === 'ovpri')
                                    <td>
                                        <span class="kmsar-ref">{{ $row->reference_number }}</span>
                                    </td>
                                @endif
                                <td>{{ $row->primaryAuthor?->name ?? '—' }}</td>
                                @if ($reportScope === 'ovpri')
                                    <td>{{ $row->motherCollege ? trim(($row->motherCollege->code ?? '').' — '.($row->motherCollege->name ?? '')) : '—' }}</td>
                                @endif
                                <td class="kmsar-table-cell-sub">{{ $reportScope === 'college' ? $reportGenerator->coAuthorsCommaSeparated($row) : $reportGenerator->coAuthorsLine($row) }}</td>
                                @if ($reportScope === 'ovpri')
                                    <td>{{ str($row->title)->limit(60) }}</td>
                                    <td>{{ $reportGenerator->classificationLabel($row->research_classification) }}</td>
                                    <td class="kmsar-table-cell-sub">
                                        <span style="display:block;">{{ $reportGenerator->statusLabel($row->status) }}</span>
                                        <span style="display:block; font-size:var(--text-xs); color:var(--color-text-muted); margin-top:0.25rem;">
                                            {{ $reportGenerator->journalConferencePresentation($row) }}
                                        </span>
                                    </td>
                                @else
                                    <td>{{ str($row->title)->limit(60) }}</td>
                                    <td class="kmsar-table-cell-sub">{{ $reportGenerator->statusLabel($row->status) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{ $reportScope === 'ovpri' ? 7 : 4 }}"
                                    class="kmsar-body"
                                    style="text-align:center; padding:var(--space-6);"
                                >
                                    {{ __('No records to preview.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        @media (max-width: 1280px) {
            .kmsar-reports-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
        @media (max-width: 1024px) {
            .kmsar-reports-stats {
                grid-template-columns: 1fr !important;
            }
        }
        @media (max-width: 640px) {
            .kmsar-reports-filter-row {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
@endsection

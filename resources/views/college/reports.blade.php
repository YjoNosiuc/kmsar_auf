@extends('layouts.app')

@section('title', __('Reports & Analytics — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Reports & Analytics') }}
@endsection

@section('content')
    @php
        $filterHidden = collect($filters ?? [])->filter(fn ($v) => $v !== null && $v !== '')->all();
        $statusOpts = [
            'proposal' => __('Proposal / abstract'),
            'ongoing' => __('Ongoing'),
            'completed_unpublished' => __('Completed (unpublished)'),
            'presented_internal' => __('Presented (internal)'),
            'presented_external' => __('Presented (external)'),
            'published_non_indexed' => __('Published (non-indexed)'),
            'published_scopus' => __('Published (Scopus / ISI)'),
            'patent_submitted' => __('Patent submitted'),
            'patent_granted' => __('Patent granted'),
        ];
        $classOpts = [
            'self_funded' => __('Self-funded'),
            'internally_funded' => __('Internally funded'),
            'externally_funded' => __('Externally funded'),
            'thesis' => __('Thesis / dissertation'),
            'collaboration' => __('Collaboration'),
            'other' => __('Other'),
        ];
        $facultyOpts = $faculties->mapWithKeys(fn ($u) => [$u->id => $u->name])->all();
    @endphp

    {{-- Page header --}}
    <div style="margin-bottom:24px;">
        <nav style="font-size:12px;color:#94A3B8;margin-bottom:6px;">
            <span>{{ __('Reports & Analytics') }}</span>
        </nav>
        <h1 style="font-size:28px;font-weight:700;color:#0F172A;margin:0 0 4px;">{{ __('Reports & Analytics') }}</h1>
        <p style="font-size:14px;color:#64748B;margin:0;">{{ auth()->user()->college?->name ?? '' }} — {{ __('Research Reports') }}</p>
    </div>

    @if (session('success'))
        <x-alert type="success" :message="session('success')" class="mb-6" />
    @endif
    @if ($errors->any())
        <x-alert type="danger" class="mb-6">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </x-alert>
    @endif

    {{-- Filter bar --}}
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:20px 24px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:14px;font-weight:600;color:#0F172A;">{{ __('Filter Report') }}</span>
                <span style="font-size:12px;color:#94A3B8;">{{ __('Leave blank to include all records') }}</span>
            </div>
            <a href="{{ route('reports.index') }}" style="font-size:12px;color:#64748B;text-decoration:none;">{{ __('Reset filters') }}</a>
        </div>
        <form method="get" action="{{ route('reports.index') }}">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;align-items:end;">
                <div>
                    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;display:block;margin-bottom:6px;">{{ __('Faculty') }}</label>
                    <select name="faculty" style="width:100%;padding:8px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#0F172A;background:#fff;font-family:inherit;">
                        <option value="">{{ __('All Faculty') }}</option>
                        @foreach ($facultyOpts as $id => $name)
                            <option value="{{ $id }}" @selected(($filters['faculty'] ?? '') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;display:block;margin-bottom:6px;">{{ __('Classification') }}</label>
                    <select name="research_classification" style="width:100%;padding:8px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#0F172A;background:#fff;font-family:inherit;">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($classOpts as $val => $label)
                            <option value="{{ $val }}" @selected(($filters['research_classification'] ?? '') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;display:block;margin-bottom:6px;">{{ __('Progress Status') }}</label>
                    <select name="status" style="width:100%;padding:8px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#0F172A;background:#fff;font-family:inherit;">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($statusOpts as $val => $label)
                            <option value="{{ $val }}" @selected(($filters['status'] ?? '') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;display:block;margin-bottom:6px;">{{ __('Date From') }}</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                        style="width:100%;padding:8px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#0F172A;background:#fff;font-family:inherit;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;display:block;margin-bottom:6px;">{{ __('Date To') }}</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                        style="width:100%;padding:8px 10px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;color:#0F172A;background:#fff;font-family:inherit;box-sizing:border-box;">
                </div>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit"
                        style="width:100%;padding:9px 20px;background:#1E3A8A;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;">
                        {{ __('Apply') }}
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Stat cards --}}
    @php
        $totalResearch = $reportStats['matching'] ?? 0;
        $published = $reportStats['published'] ?? 0;
        $presented = $reportStats['presented'] ?? 0;
    @endphp
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px;">
        <div style="background:#fff;border:1px solid #E2E8F0;border-top:3px solid #1E3A8A;border-radius:10px;padding:20px 24px;">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#1E3A8A;margin-bottom:8px;">{{ __('Total Research') }}</div>
            <div style="font-size:36px;font-weight:700;color:#1E3A8A;line-height:1;">{{ $totalResearch }}</div>
        </div>
        <div style="background:#fff;border:1px solid #E2E8F0;border-top:3px solid #D4AF37;border-radius:10px;padding:20px 24px;">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#D4AF37;margin-bottom:8px;">{{ __('Published') }}</div>
            <div style="font-size:36px;font-weight:700;color:#D4AF37;line-height:1;">{{ $published }}</div>
        </div>
        <div style="background:#fff;border:1px solid #E2E8F0;border-top:3px solid #059669;border-radius:10px;padding:20px 24px;">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#059669;margin-bottom:8px;">{{ __('Presented') }}</div>
            <div style="font-size:36px;font-weight:700;color:#059669;line-height:1;">{{ $presented }}</div>
        </div>
    </div>

    {{-- Data preview + export --}}
    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid #E2E8F0;flex-wrap:wrap;gap:10px;">
            <div>
                <div style="font-size:15px;font-weight:600;color:#0F172A;">{{ __('Data Preview') }}</div>
                <div style="font-size:12px;color:#94A3B8;margin-top:2px;">{{ __('Showing first') }} {{ $preview->count() }} {{ __('records') }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <form method="post" action="{{ route('reports.export') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="report_type" value="college">
                    <input type="hidden" name="format" value="excel">
                    @foreach ($filterHidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#059669;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;">
                        ↓ {{ __('Excel') }}
                    </button>
                </form>
                <form method="post" action="{{ route('reports.export') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="report_type" value="college">
                    <input type="hidden" name="format" value="pdf">
                    @foreach ($filterHidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#DC2626;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;">
                        ↓ {{ __('PDF') }}
                    </button>
                </form>
            </div>
        </div>
        <div class="kmsar-table-wrap">
            <table class="kmsar-table" style="width:100%;">
                <thead>
                    <tr>
                        <th scope="col" style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;">{{ __('Reference') }}</th>
                        <th scope="col" style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;">{{ __("Author's Name") }}</th>
                        <th scope="col" style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;">{{ __('Co-Authors') }}</th>
                        <th scope="col" style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;">{{ __('Title of Research') }}</th>
                        <th scope="col" style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;">{{ __('Classification') }}</th>
                        <th scope="col" style="text-transform:uppercase;font-size:11px;letter-spacing:.06em;">{{ __('Status / Journal') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview as $row)
                        <tr>
                            <td>
                                <span class="kmsar-ref">{{ $row->reference_number }}</span>
                            </td>
                            <td>{{ $row->primaryAuthor?->name ?? '—' }}</td>
                            <td class="kmsar-table-cell-sub">{{ $reportGenerator->coAuthorsCommaSeparated($row) }}</td>
                            <td class="max-w-xs">{{ str($row->title)->limit(64) }}</td>
                            <td>{{ $reportGenerator->classificationLabel($row->research_classification) }}</td>
                            <td class="kmsar-table-cell-sub">
                                <span style="display:block;">{{ $reportGenerator->statusLabel($row->status) }}</span>
                                <span style="display:block; font-size:var(--text-xs); color:var(--color-text-muted); margin-top:0.25rem;">
                                    {{ $reportGenerator->journalConferencePresentation($row) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center kmsar-body" style="padding:var(--space-6);">
                                {{ __('No records match the current filters.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

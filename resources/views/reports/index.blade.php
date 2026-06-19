@extends('layouts.app')

@section('title', __('Reports & Analytics — ') . config('app.name', 'KMSAR'))

@section('navbar-context')
    {{ __('Reports & Analytics') }}
@endsection

@section('content')
    @php
        $exportFilters = array_merge(['include_rejected' => '0'], $filters ?? []);
        $filterHidden = collect($exportFilters)->filter(fn ($v, $k) => $k === 'include_rejected' || ($v !== null && $v !== ''))->all();
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
            'thesis_dissertation',
            'collaboration',
            'other',
        ];
        $approvalStageKeys = [
            'draft' => __('Draft'),
            'dean_review' => __('Dean review'),
            'ovpri_review' => __('OVPRI review'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ];
        $academicYears = range((int) now()->year + 1, (int) now()->year - 14);
        $stats = $reportStats ?? (
            $reportScope === 'college'
                ? ['matching' => $totalCount, 'published' => 0, 'presented' => 0]
                : ['matching' => $totalCount, 'scopus' => 0, 'colleges_or_faculty' => 0]
        );
        $exportReportType = $reportScope === 'ovpri' ? 'ovpri' : 'college';
        $page = max(1, (int) ($page ?? 1));
        $perPage = max(10, (int) ($perPage ?? 10));
        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $perPage) : 1;
        $rangeStart = $totalCount > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $rangeEnd = min($page * $perPage, $totalCount);
        $paginationQuery = collect(request()->query())->except('page')->all();
    @endphp

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

    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;padding:20px 24px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
            <div>
                <span style="font-size:13px;font-weight:600;color:#0F172A;">{{ __('Filter Report') }}</span>
                <span style="font-size:12px;color:#94A3B8;margin-left:8px;">{{ __('Leave blank to include all records') }}</span>
            </div>
            <a href="{{ route('reports.index') }}" style="font-size:12px;color:#94A3B8;text-decoration:none;">{{ __('Reset filters') }}</a>
        </div>
        <form method="GET" action="{{ route('reports.index') }}">
            <div class="kmsar-reports-filter-grid" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;align-items:flex-end;">
                @if ($reportScope === 'ovpri')
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="college_id">{{ __('College') }}</label>
                        <select id="college_id" name="college_id" class="kmsar-select" style="width:100%;">
                            <option value="">{{ __('All Colleges') }}</option>
                            @foreach ($colleges as $c)
                                <option value="{{ $c->id }}" @selected(($filters['college_id'] ?? '') == (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="faculty">{{ __('Faculty') }}</label>
                        <select id="faculty" name="faculty" class="kmsar-select" style="width:100%;">
                            <option value="">{{ __('All Faculty') }}</option>
                            @foreach ($faculties as $f)
                                <option value="{{ $f->id }}" @selected(($filters['faculty'] ?? '') == (string) $f->id)>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="research_classification">{{ __('Classification') }}</label>
                    <select id="research_classification" name="research_classification" class="kmsar-select" style="width:100%;">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($classificationKeys as $ck)
                            <option value="{{ $ck }}" @selected(($filters['research_classification'] ?? '') === $ck)>{{ $reportGenerator->classificationLabel($ck) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="status">{{ __('Research progress') }}</label>
                    <select id="status" name="status" class="kmsar-select" style="width:100%;">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($statusKeys as $sk)
                            <option value="{{ $sk }}" @selected(($filters['status'] ?? '') === $sk)>{{ $reportGenerator->statusLabel($sk) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="approval_stage">{{ __('Approval status') }}</label>
                    <select id="approval_stage" name="approval_stage" class="kmsar-select" style="width:100%;">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($approvalStageKeys as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['approval_stage'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="sdg">{{ __('SDG') }}</label>
                    <select id="sdg" name="sdg" class="kmsar-select" style="width:100%;">
                        <option value="">{{ __('All SDGs') }}</option>
                        @foreach (range(1, 17) as $sdg)
                            <option value="{{ $sdg }}" @selected(($filters['sdg'] ?? '') == (string) $sdg)>{{ __('SDG :n', ['n' => $sdg]) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="funding_agency">{{ __('Funding agency') }}</label>
                    <input id="funding_agency" type="text" name="funding_agency" class="kmsar-input" style="width:100%;" value="{{ $filters['funding_agency'] ?? '' }}" placeholder="{{ __('e.g. DOST, CHED') }}">
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="academic_year">{{ __('Academic year') }}</label>
                    <select id="academic_year" name="academic_year" class="kmsar-select" style="width:100%;">
                        <option value="">{{ __('All years') }}</option>
                        @foreach ($academicYears as $year)
                            <option value="{{ $year }}" @selected(($filters['academic_year'] ?? '') == (string) $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="date_from">{{ __('Date from') }}</label>
                    <input id="date_from" type="date" name="date_from" class="kmsar-input" style="width:100%;" value="{{ $filters['date_from'] ?? '' }}">
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="date_to">{{ __('Date to') }}</label>
                    <input id="date_to" type="date" name="date_to" class="kmsar-input" style="width:100%;" value="{{ $filters['date_to'] ?? '' }}">
                </div>

                <div>
                    <label style="display:block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#94A3B8;margin-bottom:5px;" for="per_page">{{ __('Per page') }}</label>
                    <select id="per_page" name="per_page" class="kmsar-select" style="width:100%;">
                        @foreach ([10, 25, 50] as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="grid-column:span 2;">
                    <input type="hidden" name="include_rejected" value="0">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#475569;cursor:pointer;margin-top:22px;">
                        <input type="checkbox" name="include_rejected" value="1" @checked(($filters['include_rejected'] ?? '0') === '1') style="width:16px;height:16px;accent-color:#1E3A8A;">
                        {{ __('Include rejected records in preview and export') }}
                    </label>
                </div>

                <div>
                    <button type="submit" style="padding:9px 20px;background:#1E3A8A;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;font-family:inherit;margin-top:22px;">{{ __('Apply') }}</button>
                </div>
            </div>
        </form>
    </div>

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

    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:12px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="font-size:14px;font-weight:600;color:#0F172A;margin:0;">{{ __('Data Preview') }}</h3>
                <p style="font-size:12px;color:#94A3B8;margin:2px 0 0;">
                    @if ($totalCount > 0)
                        {{ __('Showing :start–:end of :total records', ['start' => number_format($rangeStart), 'end' => number_format($rangeEnd), 'total' => number_format($totalCount)]) }}
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
                    <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#059669;color:#fff;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">{{ __('Excel') }}</button>
                </form>
                <form method="post" action="{{ route('reports.export') }}" style="display:inline;margin:0;">
                    @csrf
                    <input type="hidden" name="report_type" value="{{ $exportReportType }}">
                    <input type="hidden" name="format" value="pdf">
                    @foreach ($filterHidden as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#DC2626;color:#fff;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">{{ __('PDF') }}</button>
                </form>
            </div>
        </div>
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
                            <th scope="col">{{ __('Research Progress') }}</th>
                        @else
                            <th scope="col">{{ __('Title of Research') }}</th>
                            <th scope="col">{{ __('Research Progress') }}</th>
                        @endif
                        <th scope="col">{{ __('Approval') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview as $row)
                        <tr>
                            @if ($reportScope === 'ovpri')
                                <td><span class="kmsar-ref">{{ $row->reference_number }}</span></td>
                            @endif
                            <td>{{ $row->primaryAuthor?->name ?? '—' }}</td>
                            @if ($reportScope === 'ovpri')
                                <td>{{ $row->motherCollege ? trim(($row->motherCollege->code ?? '').' — '.($row->motherCollege->name ?? '')) : '—' }}</td>
                            @endif
                            <td class="kmsar-table-cell-sub">{{ $reportScope === 'college' ? $reportGenerator->coAuthorsCommaSeparated($row) : $reportGenerator->coAuthorsLine($row) }}</td>
                            @if ($reportScope === 'ovpri')
                                <td>{{ str($row->title)->limit(60) }}</td>
                                <td>{{ $reportGenerator->classificationLabel($row->research_classification) }}</td>
                                <td class="kmsar-table-cell-sub">{{ $reportGenerator->statusLabel($row->status) }}</td>
                            @else
                                <td>{{ str($row->title)->limit(60) }}</td>
                                <td class="kmsar-table-cell-sub">{{ $reportGenerator->statusLabel($row->status) }}</td>
                            @endif
                            <td>
                                @if ($row->approval_stage === 'rejected')
                                    <span class="kmsar-badge kmsar-badge--rejected">{{ __('Rejected') }}</span>
                                @else
                                    <span class="kmsar-badge kmsar-badge--draft">{{ $approvalStageKeys[$row->approval_stage] ?? ucwords(str_replace('_', ' ', $row->approval_stage)) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $reportScope === 'ovpri' ? 8 : 5 }}" class="kmsar-body" style="text-align:center;padding:var(--space-6);">{{ __('No records to preview.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($totalCount > $perPage)
            <div style="padding:14px 20px;border-top:1px solid #E2E8F0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <span style="font-size:12px;color:#64748B;">{{ __('Page :page of :pages', ['page' => $page, 'pages' => $totalPages]) }}</span>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    @if ($page > 1)
                        <a href="{{ route('reports.index', array_merge($paginationQuery, ['page' => $page - 1])) }}" style="padding:7px 14px;border:1px solid #CBD5E1;border-radius:8px;font-size:12px;font-weight:600;color:#475569;text-decoration:none;">{{ __('Previous') }}</a>
                    @endif
                    @if ($page < $totalPages)
                        <a href="{{ route('reports.index', array_merge($paginationQuery, ['page' => $page + 1])) }}" style="padding:7px 14px;background:#1E3A8A;color:#fff;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;">{{ __('Load more') }}</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <style>
        @media (max-width: 1280px) {
            .kmsar-reports-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
        @media (max-width: 1024px) {
            .kmsar-reports-stats { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 640px) {
            .kmsar-reports-filter-grid { grid-template-columns: 1fr !important; }
        }
    </style>
@endsection

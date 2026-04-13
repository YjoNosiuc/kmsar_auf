@inject('reportGenerator', \App\Services\ReportGeneratorService::class)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $reportTitle }}</title>
    <style>
        @page { margin: 44px 32px 52px 32px; }
    </style>
</head>
<body style="font-family: DejaVu Sans, Helvetica, Arial, sans-serif; margin: 0; padding: 0; color: #0f172a;">

@php
    $isOvpri = ($report_type ?? '') === 'ovpri' || ($role ?? '') === 'ovpri';
    $records = $records ?? collect();
    $generatedAtStr = $generatedAt instanceof \Carbon\CarbonInterface
        ? $generatedAt->format('Y-m-d H:i')
        : (string) $generatedAt;
@endphp

    <div style="border-bottom: 2px solid #1E3A8A; padding-bottom: 10px; margin-bottom: 12px;">
        <div style="font-size: 11px; font-weight: 700; color: #1E3A8A; letter-spacing: 0.04em; text-transform: uppercase;">ANGELES UNIVERSITY FOUNDATION</div>
        <div style="font-size: 8px; color: #64748b; margin-top: 4px;">OVPRI — Knowledge Management System for Academic Research</div>
        <div style="font-size: 12px; font-weight: 700; color: #0f172a; margin: 10px 0 6px 0;">{{ $reportTitle }}</div>
        <div style="font-size: 8px; color: #475569; margin-bottom: 3px;">{{ __('Generated') }}: {{ $generatedAtStr }}</div>
        <div style="font-size: 8px; color: #334155; margin-top: 8px; padding: 8px 10px; background: #F1F5F9; border: 1px solid #E2E8F0;">
            <strong style="color: #1e293b;">{{ __('Filters applied') }}</strong>
            @if (! empty($filters) && is_array($filters))
                @foreach ($filters as $line)
                    <div style="margin-top: 2px;">{{ $line }}</div>
                @endforeach
            @else
                <div style="margin-top: 2px;">{{ __('None (all matching records)') }}</div>
            @endif
        </div>
    </div>

    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr>
                <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700; width: 28px;">#</th>
                <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700; width: 88px;">{{ __('Reference No.') }}</th>
                @if ($isOvpri)
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __("Author's Name") }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('College') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Other Affiliations') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Co-Authors') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Title of Research') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Registration Type') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Classification') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Journal/Conference Presentation') }}</th>
                @else
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Faculty') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __("Author's Name") }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Co-Authors') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Registration Type') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Title of Research') }}</th>
                    <th style="background: #1E3A8A; color: #ffffff; font-size: 9px; padding: 6px 8px; text-align: left; font-weight: 700;">{{ __('Journal/Conference Presentation') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $index => $research)
                @php
                    /** @var \App\Models\Research $research */
                    $n = (int) $index + 1;
                    $rowBg = ($n % 2 === 0) ? '#F8FAFC' : '#ffffff';
                    $authorName = (string) ($research->primaryAuthor?->name ?? '—');
                    $collegeLine = $research->motherCollege
                        ? trim(($research->motherCollege->code ?? '').' — '.($research->motherCollege->name ?? ''))
                        : '—';
                    $pa = $research->primaryAuthor;
                    $facultyLine = $pa
                        ? trim(($pa->employee_number ? $pa->employee_number.' — ' : '').$pa->name)
                        : '—';
                @endphp
                <tr style="background: {{ $rowBg }};">
                    <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $n }}</td>
                    <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top; color: #D4AF37; font-family: DejaVu Sans Mono, Courier, monospace;">{{ $research->reference_number ?? '—' }}</td>
                    @if ($isOvpri)
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $authorName }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $collegeLine }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->otherCollegeAffiliations($research) }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->coAuthorsLine($research) }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $research->title }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->registrationTypeLabel($research->registration_type) }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->classificationLabel($research->research_classification) }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->journalConferencePresentation($research) }}</td>
                    @else
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $facultyLine }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $authorName }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->coAuthorsCommaSeparated($research) }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->registrationTypeLabel($research->registration_type) }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $research->title }}</td>
                        <td style="font-size: 8px; padding: 5px 8px; border-bottom: 1px solid #E2E8F0; vertical-align: top;">{{ $reportGenerator->statusLabel($research->status) }}</td>
                    @endif
                </tr>
            @empty
                <tr style="background: #ffffff;">
                    <td colspan="{{ $isOvpri ? 10 : 8 }}" style="font-size: 8px; padding: 14px 8px; border-bottom: 1px solid #E2E8F0; text-align: center; color: #64748b;">
                        {{ __('No records found for the selected filters.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
            $pdf->page_text(32, 24, 'Page {PAGE_NUM} of {PAGE_COUNT} · Generated by KMSAR · {{ $generatedAtStr }}', $font, 7);
        }
    </script>
</body>
</html>

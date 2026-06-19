@php
    $queueRoute = route('approval.queue');
    $statusLabel = ucwords(str_replace('_', ' ', (string) $research->status));
    $approvalStageLabel = ucwords(str_replace('_', ' ', (string) $research->approval_stage));
    $progressBadge = match ($research->status) {
        'published_scopus', 'published_non_indexed', 'presented_external', 'presented_internal', 'completed_unpublished' => 'approved',
        'proposal', 'ongoing' => 'pending',
        'patent_submitted', 'patent_granted' => 'info',
        default => 'draft',
    };
    $stageBadgeVariant = match ($research->approval_stage) {
        'dean_review' => 'pending',
        'ovpri_review' => 'info',
        default => 'draft',
    };
    $externalPrimary = $research->researchAuthors->where('is_primary', true)->whereNull('user_id')->first();
    $primary = $research->primaryAuthor;
    $name = $externalPrimary?->name ?? $primary?->name ?? '';
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $initials = '';
    if (count($parts) >= 2) {
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
    } elseif (isset($parts[0]) && $parts[0] !== '') {
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 2));
    }
    $collegeProgram = '—';
    if ($externalPrimary) {
        $bits = array_filter([$externalPrimary->college_text, $externalPrimary->program]);
        $collegeProgram = $bits !== [] ? implode(' — ', $bits) : '—';
    } elseif ($primary) {
        $c = $primary->college;
        $p = $primary->program;
        if ($c && $p) {
            $collegeProgram = $c->name.' — '.($p->name ?? $p->code ?? '');
        } elseif ($c) {
            $collegeProgram = $c->name;
        } elseif ($p) {
            $collegeProgram = $p->name ?? $p->code ?? '—';
        }
    }
    $fmtSize = static function (?int $bytes): string {
        if ($bytes === null || $bytes < 0) {
            return '—';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    };
@endphp

@extends('layouts.app')

@section('title', $research->reference_number)

@section('navbar-context')
    {{ __('College') }}
@endsection

@section('content')
    <div
        x-data="{ tab: 'info', showEndorse: {{ ($errors->has('remarks') && old('_form') === 'endorse') ? 'true' : 'false' }}, showReturn: false, showReject: false }"
        @keydown.escape.window="showEndorse = false; showReturn = false; showReject = false"
    >
        {{-- PAGE HEADER --}}
        <div class="kmsar-page-header">
            <div>
                {{-- Breadcrumb --}}
                <div class="kmsar-breadcrumb">
                    <a href="{{ $queueRoute }}"
                       class="kmsar-breadcrumb-link">
                       {{ __('Approval Queue') }}
                    </a>
                    <span class="kmsar-breadcrumb-sep">/</span>
                    <span class="kmsar-breadcrumb-current">
                        {{ $research->reference_number }}
                    </span>
                </div>

                {{-- Reference Number in gold --}}
                <div class="kmsar-ref"
                     style="font-size: var(--text-2xl);
                            margin-bottom: 0.375rem;">
                    {{ $research->reference_number }}
                </div>

                {{-- Title in bold dark --}}
                <h1 style="font-size: var(--text-xl);
                           font-weight: 700;
                           color: var(--color-text-primary);
                           margin-bottom: 0.375rem;
                           text-transform: uppercase;">
                    {{ $research->title }}
                </h1>

                {{-- Submitted date + Primary author --}}
                <div style="font-size: var(--text-xs);
                            color: var(--color-text-muted);
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;">
                    <span>
                        {{ __('Submitted') }}
                        {{ $research->created_at?->format('M d, Y') ?? '—' }}
                    </span>
                    @if($research->primaryAuthor)
                        <span>·</span>
                        <span style="font-weight: 500;
                                     text-transform: uppercase;">
                            @if($research->primaryAuthor->first_name)
                                {{ $research->primaryAuthor->first_name }}
                                {{ $research->primaryAuthor->last_name }}
                            @else
                                {{ $research->primaryAuthor->name }}
                            @endif
                        </span>
                    @endif
                </div>
            </div>

            {{-- Action buttons — keep role-specific buttons --}}
            <div class="kmsar-page-header-actions">
                <a href="{{ $queueRoute }}" class="kmsar-btn kmsar-btn--md kmsar-btn--outline" role="button">{{ __('Back to Queue') }}</a>
                @if ($research->approval_stage === 'dean_review')
                    <button type="button" class="kmsar-btn kmsar-btn--md kmsar-btn--success" @click="showEndorse = true">{{ __('Endorse') }}</button>
                    <button type="button" class="kmsar-btn kmsar-btn--md kmsar-btn--warning" @click="showReturn = true">{{ __('Return') }}</button>
                    <button type="button" class="kmsar-btn kmsar-btn--md kmsar-btn--danger-outline" @click="showReject = true">{{ __('Reject') }}</button>
                @endif
            </div>
        </div>

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

        <div
            class="kmsar-review-grid"
            style="display:grid;grid-template-columns:2fr 1fr;gap:20px;align-items:flex-start;"
        >
            {{-- LEFT COLUMN --}}
            <div class="min-w-0">
                <div class="kmsar-tabs" role="tablist" aria-label="{{ __('Review sections') }}">
                    <button
                        type="button"
                        role="tab"
                        class="kmsar-tab"
                        :class="{ 'active': tab === 'info' }"
                        :aria-selected="tab === 'info'"
                        @click="tab = 'info'"
                    >{{ __('Research Info') }}</button>
                    <button
                        type="button"
                        role="tab"
                        class="kmsar-tab"
                        :class="{ 'active': tab === 'documents' }"
                        :aria-selected="tab === 'documents'"
                        @click="tab = 'documents'"
                    >{{ __('Documents') }}</button>
                    <button
                        type="button"
                        role="tab"
                        class="kmsar-tab"
                        :class="{ 'active': tab === 'history' }"
                        :aria-selected="tab === 'history'"
                        @click="tab = 'history'"
                    >{{ __('Approval History') }}</button>
                </div>

                {{-- Tab 1: Research Info --}}
                @include('partials.research-detail-card', ['research' => $research])

                {{-- Tab 2: Documents --}}
                <div x-show="tab === 'documents'" x-cloak style="display: none;" role="tabpanel">
                    <div class="kmsar-card kmsar-card--accent-primary">
                        <div class="kmsar-card-body" style="padding:0;">
                            @if ($research->documents->isEmpty())
                                <div style="padding:var(--space-10) var(--space-6);text-align:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:3rem;height:3rem;margin:0 auto;color:var(--color-text-muted);opacity:0.6;" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V12.75a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <p class="kmsar-body" style="margin-top:var(--space-4);color:var(--color-text-muted);">{{ __('No documents uploaded yet') }}</p>
                                </div>
                            @else
                                <div class="kmsar-table-wrap">
                                    <table class="kmsar-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ __('File') }}</th>
                                                <th scope="col">{{ __('Size') }}</th>
                                                <th scope="col">{{ __('Uploaded') }}</th>
                                                <th scope="col"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($research->documents as $document)
                                                <tr>
                                                    <td class="font-medium">{{ $document->original_filename }}</td>
                                                    <td>@if($document->external_link) — @else {{ $fmtSize($document->file_size_bytes) }} @endif</td>
                                                    <td>{{ $document->created_at?->format('M d, Y') ?? '—' }}</td>
                                                    <td class="text-right" style="white-space:nowrap;">
                                                        @if($document->external_link)
                                                            <a href="{{ $document->external_link }}" target="_blank" rel="noopener noreferrer" class="kmsar-btn kmsar-btn--xs kmsar-btn--primary">{{ __('Open Link') }}</a>
                                                        @else
                                                            <div style="display: flex; 
                align-items: center; 
                gap: 0.5rem;
                justify-content: flex-end;">
                                                                <button type="button"
                                                                    onclick="kmsarOpenPreviewModal('{{ route('approval.documents.preview', [$research, $document]) }}', '{{ addslashes($document->original_filename) }}')"
                                                                    class="kmsar-btn kmsar-btn--outline kmsar-btn--sm">
                                                                    {{ __('Preview') }}
                                                                </button>
                                                                <a href="{{ route('approval.documents.download', [$research, $document]) }}"
                                                                    class="kmsar-btn kmsar-btn--secondary kmsar-btn--sm"
                                                                    style="border: 1.5px solid var(--color-border) !important;">
                                                                    {{ __('Download') }}
                                                                </a>
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tab 3: Approval History --}}
                <div x-show="tab === 'history'" x-cloak style="display: none;" role="tabpanel">
                    <div class="kmsar-card kmsar-card--accent-primary">
                        <div class="kmsar-card-body">
                            @if ($research->approvals->isEmpty())
                                <p class="kmsar-body" style="margin:0;color:var(--color-text-muted);">{{ __('No approval actions recorded yet.') }}</p>
                            @else
                                <div class="kmsar-timeline">
                                    @foreach ($research->approvals as $row)
                                        @php
                                            $stageLabel = $row->stage === 'dean' ? __('Dean') : __('OVPRI');
                                            $actionBadge = match ($row->action) {
                                                'endorsed', 'approved' => 'approved',
                                                'returned' => 'pending',
                                                'rejected' => 'rejected',
                                                default => 'info',
                                            };
                                            $actionHuman = ucwords(str_replace('_', ' ', (string) $row->action));
                                            $dotStyle = match ($row->action) {
                                                'endorsed', 'approved' => '',
                                                'returned' => 'background:var(--color-warning);border-color:var(--color-warning);',
                                                'rejected' => 'background:var(--color-danger);border-color:var(--color-danger);',
                                                default => '',
                                            };
                                            $dotClass = in_array($row->action, ['endorsed', 'approved'], true)
                                                ? 'kmsar-timeline-dot kmsar-timeline-dot--done'
                                                : 'kmsar-timeline-dot';
                                        @endphp
                                        <div class="kmsar-timeline-item">
                                            <div
                                                class="{{ $dotClass }}"
                                                style="{{ $dotStyle }}"
                                                aria-hidden="true"
                                            ></div>
                                            <div class="kmsar-timeline-content">
                                                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;">
                                                    <span class="kmsar-badge kmsar-badge--info kmsar-badge--square">{{ $stageLabel }}</span>
                                                    <span class="kmsar-badge kmsar-badge--{{ $actionBadge }} kmsar-badge--square">{{ $actionHuman }}</span>
                                                </div>
                                                <div class="kmsar-timeline-title" style="margin-top:0.5rem;">
                                                    {{ $row->approver?->name ?? '—' }}
                                                    <span class="kmsar-timeline-meta" style="display:inline;margin-left:0.35rem;">{{ $row->acted_at?->format('M d, Y g:i a') ?? '—' }}</span>
                                                </div>
                                                @if ($row->remarks)
                                                    <blockquote class="kmsar-timeline-remark" style="border-left-color:var(--color-border-strong);background:var(--color-surface);font-style:italic;">{{ $row->remarks }}</blockquote>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT COLUMN: sticky sidebar --}}
            <div class="min-w-0" style="position:sticky;top:80px;display:flex;flex-direction:column;gap:16px;">
                <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;overflow:hidden;border-top:3px solid #D4AF37;padding:16px 20px;">
                    <h2 class="kmsar-card-title" style="margin:0 0 12px 0;">{{ __('Submission Summary') }}</h2>
                    <table style="width:100%;font-size:13px;border-collapse:collapse;">
                        <tbody>
                            <tr>
                                <td style="color:#94A3B8;padding:6px 0;width:45%;vertical-align:middle;">{{ __('Status') }}</td>
                                <td style="padding:6px 0;vertical-align:middle;"><span class="kmsar-badge kmsar-badge--{{ $progressBadge }} kmsar-badge--square">{{ $statusLabel }}</span></td>
                            </tr>
                            <tr>
                                <td style="color:#94A3B8;padding:6px 0;vertical-align:middle;">{{ __('Stage') }}</td>
                                <td style="padding:6px 0;vertical-align:middle;"><span class="kmsar-badge kmsar-badge--{{ $stageBadgeVariant }} kmsar-badge--square">{{ $approvalStageLabel }}</span></td>
                            </tr>
                            <tr>
                                <td style="color:#94A3B8;padding:6px 0;vertical-align:middle;">{{ __('Submitted') }}</td>
                                <td style="padding:6px 0;font-weight:500;color:var(--color-text-primary);">{{ $research->created_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td style="color:#94A3B8;padding:6px 0;vertical-align:middle;">{{ __('Last Updated') }}</td>
                                <td style="padding:6px 0;font-weight:500;color:var(--color-text-primary);">{{ $research->updated_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td style="color:#94A3B8;padding:6px 0;vertical-align:middle;">{{ __('Revisions') }}</td>
                                <td style="padding:6px 0;font-weight:500;color:var(--color-text-primary);">{{ $research->revision_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;overflow:hidden;border-top:3px solid #1E3A8A;padding:16px 20px;">
                    <h2 class="kmsar-card-title" style="margin:0 0 12px 0;">{{ __('Primary Author') }}</h2>
                    @if ($externalPrimary)
                        <div style="font-size:14px;font-weight:600;color:#0F172A;">{{ $externalPrimary->name }}</div>
                        @if ($externalPrimary->employee_number)
                            <div style="font-size:12px;color:#475569;">{{ $externalPrimary->employee_number }}</div>
                        @endif
                        @if ($externalPrimary->college_text)
                            <div style="font-size:12px;color:#475569;">{{ $externalPrimary->college_text }}</div>
                        @endif
                        @if ($externalPrimary->program)
                            <div style="font-size:12px;color:#475569;">{{ $externalPrimary->program }}</div>
                        @endif
                        @if ($externalPrimary->email)
                            <div style="font-size:12px;color:#94A3B8;">{{ $externalPrimary->email }}</div>
                        @endif
                        <div style="font-size:11px;color:#D97706;margin-top:4px;font-weight:600;">{{ __('Registered by:') }} {{ $research->primaryAuthor?->name }}</div>
                    @else
                        <div style="display:flex;gap:var(--space-4);align-items:flex-start;">
                            <div class="kmsar-avatar kmsar-avatar--lg kmsar-avatar--blue" aria-hidden="true">{{ $initials ?: '—' }}</div>
                            <div class="min-w-0">
                                <div style="font-weight:600;color:var(--color-text-primary);font-size:var(--text-sm);">{{ $primary?->name ?? '—' }}</div>
                                @if ($primary?->email)
                                    <div class="kmsar-table-cell-sub" style="margin-top:0.25rem;word-break:break-all;">{{ $primary->email }}</div>
                                @endif
                                <div class="kmsar-body" style="margin-top:0.35rem;font-size:var(--text-xs);">{{ $collegeProgram }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- MODALS --}}
        @if ($research->approval_stage === 'dean_review')
            <div x-show="showEndorse" x-cloak class="kmsar-modal-overlay" style="display: none;" @click.self="showEndorse = false">
                <div class="kmsar-modal kmsar-modal--sm" @click.stop role="dialog" aria-modal="true" aria-labelledby="kmsar-endorse-title">
                    <form method="post" action="{{ route('approval.endorse', $research) }}">
                        @csrf
                        <input type="hidden" name="_form" value="endorse">
                        <div class="kmsar-modal-header">
                            <h2 id="kmsar-endorse-title" class="kmsar-modal-title">{{ __('Endorse Submission') }}</h2>
                            <button type="button" class="kmsar-modal-close" @click="showEndorse = false" aria-label="{{ __('Close') }}">&times;</button>
                        </div>
                        <div class="kmsar-modal-body space-y-4">
                            <div class="kmsar-alert kmsar-alert--success" role="status">
                                {{ __('This research will be forwarded to OVPRI for final approval.') }}
                            </div>
                            <div>
                                <label for="endorse-remarks" class="kmsar-label" style="display:block;margin-bottom:0.35rem;">{{ __('Remarks (required)') }}</label>
                                <textarea
                                    id="endorse-remarks"
                                    name="remarks"
                                    rows="4"
                                    class="kmsar-input w-full @error('remarks') kmsar-input--error @enderror"
                                    required
                                    minlength="10"
                                    maxlength="5000"
                                    placeholder="{{ __('Add any notes for OVPRI before endorsing…') }}"
                                    style="text-transform: uppercase"
                                >{{ old('remarks') }}</textarea>
                                @error('remarks')
                                    <p class="kmsar-form-error" role="alert">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="kmsar-modal-footer flex justify-end gap-2">
                            <button type="button" class="kmsar-btn kmsar-btn--md kmsar-btn--outline" @click="showEndorse = false">{{ __('Cancel') }}</button>
                            <button type="submit" class="kmsar-btn kmsar-btn--md kmsar-btn--success">{{ __('Endorse') }}</button>
                        </div>
                    </form>
                </div>
            </div>
            <div x-show="showReturn" x-cloak class="kmsar-modal-overlay" style="display: none;" @click.self="showReturn = false">
                <div class="kmsar-modal kmsar-modal--sm" @click.stop role="dialog" aria-modal="true" aria-labelledby="kmsar-return-title">
                    <div class="kmsar-modal-header">
                        <h2 id="kmsar-return-title" class="kmsar-modal-title">{{ __('Return for revision') }}</h2>
                        <button type="button" class="kmsar-modal-close" @click="showReturn = false" aria-label="{{ __('Close') }}">&times;</button>
                    </div>
                    <form method="post" action="{{ route('approval.return', $research) }}" class="kmsar-modal-body space-y-4">
                        @csrf
                        <p class="kmsar-body" style="font-size:var(--text-xs);color:var(--color-warning);margin:0;">{{ __('Faculty will be notified and must revise before resubmitting.') }}</p>
                        <div>
                            <label for="return-remarks" class="kmsar-label" style="display:block;margin-bottom:0.35rem;">{{ __('Remarks (required)') }}</label>
                            <textarea
                                id="return-remarks"
                                name="remarks"
                                rows="4"
                                class="kmsar-input w-full"
                                required
                                minlength="10"
                                maxlength="5000"
                                placeholder="{{ __('Provide feedback for the author…') }}"
                                style="text-transform: uppercase"
                            ></textarea>
                        </div>
                        <div class="kmsar-modal-footer flex justify-end gap-2">
                            <button type="button" class="kmsar-btn kmsar-btn--md kmsar-btn--secondary" @click="showReturn = false">{{ __('Cancel') }}</button>
                            <button type="submit" class="kmsar-btn kmsar-btn--md kmsar-btn--warning">{{ __('Return') }}</button>
                        </div>
                    </form>
                </div>
            </div>
            <div x-show="showReject" x-cloak class="kmsar-modal-overlay" style="display: none;" @click.self="showReject = false">
                <div class="kmsar-modal kmsar-modal--sm" @click.stop role="dialog" aria-modal="true" aria-labelledby="kmsar-reject-title">
                    <div class="kmsar-modal-header">
                        <h2 id="kmsar-reject-title" class="kmsar-modal-title kmsar-modal-title--danger">{{ __('Reject submission') }}</h2>
                        <button type="button" class="kmsar-modal-close" @click="showReject = false" aria-label="{{ __('Close') }}">&times;</button>
                    </div>
                    <form method="post" action="{{ route('approval.reject', $research) }}" class="kmsar-modal-body space-y-4">
                        @csrf
                        <p class="kmsar-body" style="font-size:var(--text-xs);color:var(--color-danger);margin:0;font-weight:600;">{{ __('This action permanently rejects the submission.') }}</p>
                        <div>
                            <label for="reject-remarks" class="kmsar-label" style="display:block;margin-bottom:0.35rem;">{{ __('Remarks') }}</label>
                            <textarea
                                id="reject-remarks"
                                name="remarks"
                                rows="4"
                                class="kmsar-input w-full"
                                required
                                maxlength="5000"
                                placeholder="{{ __('Reason for rejection…') }}"
                                style="text-transform: uppercase"
                            ></textarea>
                        </div>
                        <div class="kmsar-modal-footer flex justify-end gap-2">
                            <button type="button" class="kmsar-btn kmsar-btn--md kmsar-btn--secondary" @click="showReject = false">{{ __('Cancel') }}</button>
                            <button type="submit" class="kmsar-btn kmsar-btn--md kmsar-btn--danger">{{ __('Reject') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div id="kmsar-preview-modal"
            style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:24px;box-sizing:border-box;">
            <div style="background:#fff;border-radius:12px;width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #E2E8F0;flex-shrink:0;">
                    <span id="kmsar-preview-modal-filename" style="font-size:13px;font-weight:600;color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:calc(100% - 48px);"></span>
                    <button type="button" onclick="kmsarOpenPreviewModal_close()"
                        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;color:#64748B;border-radius:6px;cursor:pointer;flex-shrink:0;"
                        onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div style="flex:1;overflow:auto;padding:16px;background:#F8FAFC;min-height:0;">
                    <iframe id="kmsar-preview-modal-iframe"
                        src=""
                        style="width:100%;height:75vh;border:none;border-radius:8px;background:#fff;display:block;"
                        title="{{ __('Document preview') }}">
                    </iframe>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (max-width: 1024px) {
            .kmsar-review-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
@endsection

@push('scripts')
<script>
window.kmsarOpenPreviewModal = function(url, filename) {
    var modal = document.getElementById('kmsar-preview-modal');
    var iframe = document.getElementById('kmsar-preview-modal-iframe');
    var label = document.getElementById('kmsar-preview-modal-filename');
    if (!modal || !iframe) return;
    iframe.src = url;
    if (label) label.textContent = filename || '';
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
};
window.kmsarOpenPreviewModal_close = function() {
    var modal = document.getElementById('kmsar-preview-modal');
    var iframe = document.getElementById('kmsar-preview-modal-iframe');
    if (!modal || !iframe) return;
    iframe.src = '';
    modal.style.display = 'none';
    document.body.style.overflow = '';
};
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') window.kmsarOpenPreviewModal_close();
});
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('kmsar-preview-modal');
    if (modal) modal.addEventListener('click', function(e) {
        if (e.target === modal) window.kmsarOpenPreviewModal_close();
    });
});
</script>
@endpush

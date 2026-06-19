@php
    $listRoute = route('research.index');
    $approvalStageLabel = ucwords(str_replace('_', ' ', (string) $research->approval_stage));
    $statusLabel = ucwords(str_replace('_', ' ', (string) $research->status));
    $progressBadge = match ($research->status) {
        'published_scopus', 'published_non_indexed', 'presented_external', 'presented_internal', 'completed_unpublished' => 'approved',
        'proposal', 'ongoing' => 'pending',
        'patent_submitted', 'patent_granted' => 'info',
        default => 'draft',
    };
    $stageBadgeVariant = match ($research->approval_stage) {
        'dean_review' => 'pending',
        'ovpri_review' => 'info',
        'approved' => 'approved',
        'rejected' => 'rejected',
        default => 'draft',
    };
@endphp

@extends('layouts.app')

@section('title', $research->reference_number)

@section('navbar-context')
    {{ __('Faculty') }}
@endsection

@section('content')
    <div x-data="{ tab: 'info', showUpdateProgress: false }">
        <div class="kmsar-page-header">
            <div>
                {{-- Breadcrumb --}}
                <div class="kmsar-breadcrumb">
                    <a href="{{ route('research.index') }}"
                       class="kmsar-breadcrumb-link">
                       {{ __('My Research') }}
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
                <x-button variant="secondary" href="{{ $listRoute }}">{{ __('Back to list') }}</x-button>
                @if ($research->approval_stage === 'approved')
                    @can('updateProgress', $research)
                        <x-button variant="primary" type="button" @click="showUpdateProgress = true" aria-label="{{ __('Update Progress') }}">{{ __('Update Progress') }}</x-button>
                    @endcan
                @endif
                @if ($research->approval_stage === 'draft')
                    @can('update', $research)
                        <x-button variant="secondary" href="{{ route('research.wizard.details', $research) }}">{{ __('Edit') }}</x-button>
                    @endcan

                    @can('submit', $research)
                        @if ($research->revision_count === 0)
                            <form method="post" action="{{ route('research.submit', $research) }}" class="inline">
                                @csrf
                                <x-button type="submit" variant="primary">{{ __('Submit for Review') }}</x-button>
                            </form>
                        @endif
                    @endcan

                    @if ($research->revision_count > 0)
                        @can('submit', $research)
                            <form method="post" action="{{ route('research.submit', $research) }}" class="inline">
                                @csrf
                                <x-button type="submit" variant="primary">{{ __('Revise & Resubmit') }}</x-button>
                            </form>
                        @endcan
                    @endif
                @endif

                @if ($research->approval_stage === 'rejected')
                    @can('revise', $research)
                        <form method="post" action="{{ route('research.revise', $research) }}" class="inline">
                            @csrf
                            <x-button type="submit" variant="secondary">{{ __('Revise') }}</x-button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if (session('success'))
            <x-alert type="success" :message="session('success')" class="mb-6" />
        @endif

        @if ($research->approval_stage === 'draft' && $research->revision_count > 0)
            <x-alert type="warning" class="mb-6">
                {{ __('This submission was returned for revision. Please update your documents and resubmit.') }}
            </x-alert>
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
            <div class="min-w-0">
                <div class="kmsar-tabs mb-6" role="tablist" aria-label="{{ __('Research sections') }}">
                    <button
                        type="button"
                        role="tab"
                        class="kmsar-tab"
                        :class="{ 'active': tab === 'info' }"
                        :aria-selected="tab === 'info'"
                        @click="tab = 'info'"
                    >{{ __('Research info') }}</button>
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
                    >{{ __('Approval history') }}</button>
                </div>

                {{-- Tab 1: Research info --}}
                @include('partials.research-detail-card', ['research' => $research])

                {{-- Tab 2: Documents --}}
                <div x-show="tab === 'documents'" x-cloak class="space-y-6" style="display: none;" role="tabpanel">
                    <div class="kmsar-card kmsar-card--accent-primary">
                        <div class="kmsar-card-header">
                            <h2 class="kmsar-card-title">{{ __('Documents') }}</h2>
                        </div>
                        <div class="kmsar-card-body">
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
                                        @forelse ($research->documents as $document)
                                            <tr>
                                                <td class="font-medium">{{ $document->original_filename }}</td>
                                                <td>
                                                    @if($document->external_link)
                                                        —
                                                    @else
                                                        {{ number_format(max(0, $document->file_size_bytes) / 1024, 1) }} KB
                                                    @endif
                                                </td>
                                                <td>{{ $document->created_at?->format('M d, Y') ?? '—' }}</td>
                                                <td class="text-right" style="white-space: nowrap;">
                                                    <div style="display:inline-flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:8px;">
                                                        @if($document->external_link)
                                                            <a href="{{ $document->external_link }}" target="_blank" rel="noopener noreferrer"
                                                                style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;font-size:12px;font-weight:600;background:#1E3A8A;color:#fff;border-radius:6px;text-decoration:none;">
                                                                🔗 {{ __('Open Link') }}
                                                            </a>
                                                        @else
                                                            <div style="display: flex; 
                align-items: center; 
                gap: 0.5rem;
                justify-content: flex-end;">
                                                            <button type="button"
                                                                onclick="kmsarOpenPreviewModal('{{ route('documents.preview', [$research, $document]) }}', '{{ addslashes($document->original_filename) }}')"
                                                                class="kmsar-btn kmsar-btn--outline kmsar-btn--sm">
                                                                {{ __('Preview') }}
                                                            </button>
                                                            <a href="{{ route('documents.download', [$research, $document]) }}"
                                                                class="kmsar-btn kmsar-btn--secondary kmsar-btn--sm"
                                                                style="border: 1.5px solid var(--color-border) !important;">
                                                                {{ __('Download') }}
                                                            </a>
                                                            </div>
                                                        @endif
                                                        @if($research->approval_stage === 'draft' && (int) $document->uploaded_by === (int) auth()->id())
                                                            <form method="POST" action="{{ route('documents.destroy', $document) }}" style="display:inline;">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit"
                                                                    onclick="return confirm('{{ __('Delete this file?') }}')"
                                                                    style="padding:4px 10px;font-size:11px;font-weight:600;border:1px solid #FCA5A5;color:#DC2626;background:#FEF2F2;border-radius:6px;cursor:pointer;">
                                                                    {{ __('Delete') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center kmsar-body" style="padding: var(--space-6);">
                                                    {{ __('No documents uploaded yet.') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if ($research->approval_stage === 'draft')
                                @can('update', $research)
                                    <div class="mt-6 border-t border-[var(--color-border)] pt-6">
                                        <form
                                            method="post"
                                            action="{{ route('documents.upload', $research) }}"
                                            enctype="multipart/form-data"
                                            class="space-y-4"
                                        >
                                            @csrf
                                            <div class="kmsar-form-group" x-data="researchShowDocumentUpload()">
                                                <label class="kmsar-form-label">{{ __('Add document') }}</label>
                                                <div class="kmsar-tabs" style="margin-bottom: 1rem;">
                                                    <button
                                                        type="button"
                                                        class="kmsar-tab"
                                                        :class="{ 'active': uploadType === 'file' }"
                                                        @click="uploadType = 'file'"
                                                    >{{ __('Upload File') }}</button>
                                                    <button
                                                        type="button"
                                                        class="kmsar-tab"
                                                        :class="{ 'active': uploadType === 'link' }"
                                                        @click="uploadType = 'link'"
                                                    >{{ __('Add Link') }}</button>
                                                </div>
                                                <div x-show="uploadType==='file'">
                                                    <div
                                                        class="kmsar-dropzone"
                                                        @click="$refs.fileInput.click()"
                                                        @dragover.prevent
                                                        @drop.prevent="handleDrop($event)"
                                                    >
                                                        <svg class="kmsar-dropzone-icon" xmlns="http://www.w3.org/2000/svg"
                                                            fill="none" viewBox="0 0 24 24"
                                                            stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                            <path stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                                        </svg>
                                                        <p class="kmsar-dropzone-title">
                                                            <span>{{ __('Click to upload') }}</span> {{ __('or drag and drop') }}
                                                        </p>
                                                        <p class="kmsar-dropzone-hint">
                                                            {{ __('PDF, Word, Excel, Image · Max 100MB · 2 files max') }}
                                                        </p>
                                                        <input
                                                            id="research_documents"
                                                            type="file"
                                                            x-ref="fileInput"
                                                            name="files[]"
                                                            multiple
                                                            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                                            class="hidden"
                                                            style="display:none;"
                                                            @change="handleFileSelect($event)"
                                                        >
                                                        <p class="modal-file-name" style="font-size:12px;color:#475569;margin:10px 0 0;min-height:16px;"></p>
                                                    </div>
                                                    <p class="kmsar-form-hint">{{ __('Maximum 2 files per upload · PDF, Word, Excel, Image · Max 100MB each') }}</p>
                                                </div>
                                                <div x-show="uploadType==='link'" x-cloak>
                                                    <input type="url" name="external_link"
                                                        placeholder="https://drive.google.com/... or https://doi.org/..."
                                                        class="kmsar-input"
                                                        style="width:100%;">
                                                    <p class="kmsar-form-hint">{{ __('Paste a Google Drive, OneDrive, DOI, or any public link to your document.') }}</p>
                                                </div>
                                            </div>
                                            <x-button type="submit" variant="primary">{{ __('Upload') }}</x-button>
                                        </form>
                                    </div>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Tab 3: Approval history --}}
                <div x-show="tab === 'history'" x-cloak class="space-y-6" style="display: none;" role="tabpanel">
                    <div class="kmsar-card kmsar-card--accent-primary">
                        <div class="kmsar-card-header">
                            <h2 class="kmsar-card-title">{{ __('Approval history') }}</h2>
                        </div>
                        <div class="kmsar-card-body">
                            @if ($research->approvals->isEmpty())
                                <p class="kmsar-body" style="color: var(--color-text-muted); margin: 0;">{{ __('No approval actions yet.') }}</p>
                            @else
                                <div class="kmsar-timeline">
                                    @foreach ($research->approvals as $row)
                                        @php
                                            $stageLabel = match ($row->stage) {
                                                'dean' => __('Dean'),
                                                'ovpri' => __('OVPRI'),
                                                'faculty' => __('Faculty'),
                                                default => ucwords(str_replace('_', ' ', (string) $row->stage)),
                                            };
                                            $isProgressUpdate = $row->action === 'progress_update';
                                            $actionBadge = match ($row->action) {
                                                'endorsed', 'approved' => 'approved',
                                                'returned' => 'returned',
                                                'rejected' => 'rejected',
                                                'progress_update' => 'info',
                                                default => 'info',
                                            };
                                            $dotClass = $isProgressUpdate
                                                ? 'kmsar-timeline-dot--progress'
                                                : match ($row->action) {
                                                    'endorsed', 'approved' => 'kmsar-timeline-dot--done',
                                                    'returned' => 'kmsar-timeline-dot--active',
                                                    'rejected' => 'kmsar-timeline-dot--pending',
                                                    default => 'kmsar-timeline-dot--pending',
                                                };
                                        @endphp
                                        <div class="kmsar-timeline-item">
                                            <div class="kmsar-timeline-dot {{ $dotClass }}" aria-hidden="true"></div>
                                            <div class="kmsar-timeline-content">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <x-badge status="info" square>{{ $stageLabel }}</x-badge>
                                                    @if ($isProgressUpdate)
                                                        <x-badge status="info">{{ __('Progress Update') }}</x-badge>
                                                    @else
                                                        <x-badge :status="$actionBadge">{{ str_replace('_', ' ', $row->action) }}</x-badge>
                                                    @endif
                                                </div>
                                                <div class="kmsar-timeline-title mt-2">{{ $row->approver?->name ?? '—' }}</div>
                                                <div class="kmsar-timeline-meta">{{ $row->acted_at?->format('M j, Y g:i a') ?? '—' }}</div>
                                                @if ($row->remarks)
                                                    <div class="kmsar-timeline-remark">{{ $row->remarks }}</div>
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

            {{-- Right sidebar --}}
            <div class="min-w-0" style="position:sticky;top:80px;display:flex;flex-direction:column;gap:16px;">
                <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;overflow:hidden;border-top:3px solid #D4AF37;padding:16px 20px;">
                    <h2 class="kmsar-card-title" style="margin:0 0 12px 0;">{{ __('Submission summary') }}</h2>
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
                                <td style="color:#94A3B8;padding:6px 0;vertical-align:middle;">{{ __('Last updated') }}</td>
                                <td style="padding:6px 0;font-weight:500;color:var(--color-text-primary);">{{ $research->updated_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td style="color:#94A3B8;padding:6px 0;vertical-align:middle;">{{ __('Revisions') }}</td>
                                <td style="padding:6px 0;font-weight:500;color:var(--color-text-primary);">{{ $research->revision_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($research->approval_stage === 'draft')
                    <div style="background:#fff;border:1px solid #E2E8F0;border-radius:10px;padding:16px 20px;border-top:3px solid #1E3A8A;">
                        <h2 class="kmsar-card-title" style="margin:0 0 8px 0;">{{ __('Ready to submit?') }}</h2>
                        <p class="kmsar-body" style="margin:0 0 16px;font-size:13px;color:#64748B;">{{ __('Ensure documents and details are complete before sending this record for dean review.') }}</p>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;">
                            @can('submit', $research)
                                <form method="post" action="{{ route('research.submit', $research) }}" class="inline">
                                    @csrf
                                    <button
                                        type="submit"
                                        style="padding:10px 20px;background:#1E3A8A;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;"
                                    >
                                        @if ($research->revision_count > 0)
                                            {{ __('Revise & resubmit') }}
                                        @else
                                            {{ __('Submit for review') }}
                                        @endif
                                    </button>
                                </form>
                            @endcan
                            @can('update', $research)
                                <a
                                    href="{{ route('research.wizard.details', $research) }}"
                                    style="display:inline-flex;align-items:center;padding:10px 20px;border:1px solid #CBD5E1;color:#475569;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;background:#fff;"
                                >{{ __('Edit') }}</a>
                            @endcan
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div
            x-show="showUpdateProgress"
            x-cloak
            class="kmsar-modal-overlay"
            style="display: none;"
            x-on:click.self="showUpdateProgress = false"
        >
            <div class="kmsar-modal kmsar-modal--sm" x-on:click.stop role="dialog" aria-modal="true" aria-labelledby="kmsar-update-progress-title">
                <form method="POST" action="{{ route('research.update-progress', $research) }}" enctype="multipart/form-data" style="margin:0;">
                    @csrf
                    @method('PUT')

                    <div style="background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,0.18);">

                        {{-- Header --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0;flex-shrink:0;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:36px;height:36px;background:#EFF6FF;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <svg style="width:18px;height:18px;color:#1E3A8A;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 id="kmsar-update-progress-title" style="font-size:16px;font-weight:700;color:#0F172A;margin:0;line-height:1.2;">{{ __('Update Research Progress') }}</h2>
                                    <p style="font-size:12px;color:#94A3B8;margin:0;margin-top:1px;">{{ __('Changes will notify your Dean and OVPRI') }}</p>
                                </div>
                            </div>
                            <button type="button"
                                x-on:click="showUpdateProgress = false"
                                style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;color:#94A3B8;border-radius:8px;cursor:pointer;transition:background 0.15s;"
                                onmouseover="this.style.background='#F1F5F9';this.style.color='#0F172A'"
                                onmouseout="this.style.background='transparent';this.style.color='#94A3B8'">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Divider --}}
                        <div style="height:1px;background:#F1F5F9;margin:16px 0 0;flex-shrink:0;"></div>

                        {{-- Scrollable body --}}
                        <div style="flex:1;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:20px;">

                            {{-- Info banner --}}
                            <div style="display:flex;gap:10px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 14px;">
                                <svg style="width:16px;height:16px;color:#1E3A8A;flex-shrink:0;margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p style="font-size:12px;color:#1E40AF;margin:0;line-height:1.5;">{{ __('Updating the progress status will notify your Dean and OVPRI. The record will be re-submitted for endorsement.') }}</p>
                            </div>

                            {{-- New Progress Status --}}
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;letter-spacing:0.02em;">
                                    {{ __('New Progress Status') }}
                                    <span style="color:#DC2626;">*</span>
                                </label>
                                <div style="position:relative;">
                                    <select name="status" required
                                        style="width:100%;padding:10px 36px 10px 12px;border:1.5px solid #E2E8F0;border-radius:8px;font-size:13px;color:#0F172A;background:#fff;font-family:inherit;appearance:none;cursor:pointer;transition:border-color 0.15s;box-sizing:border-box;"
                                        onfocus="this.style.borderColor='#1E3A8A';this.style.boxShadow='0 0 0 3px rgba(30,58,138,0.08)'"
                                        onblur="this.style.borderColor='#E2E8F0';this.style.boxShadow='none'">
                                        <option value="">{{ __('Select a status...') }}</option>
                                        <option value="proposal" @selected($research->status === 'proposal')>{{ __('Proposal / Abstract Stage') }}</option>
                                        <option value="ongoing" @selected($research->status === 'ongoing')>{{ __('Research in Progress') }}</option>
                                        <option value="completed_unpublished" @selected($research->status === 'completed_unpublished')>{{ __('Completed (Unpublished)') }}</option>
                                        <option value="presented_internal" @selected($research->status === 'presented_internal')>{{ __('Presented Internally') }}</option>
                                        <option value="presented_external" @selected($research->status === 'presented_external')>{{ __('Presented Externally') }}</option>
                                        <option value="published_non_indexed" @selected($research->status === 'published_non_indexed')>{{ __('Published (Non-indexed)') }}</option>
                                        <option value="published_scopus" @selected($research->status === 'published_scopus')>{{ __('Published (Scopus / ISI)') }}</option>
                                        <option value="patent_submitted" @selected($research->status === 'patent_submitted')>{{ __('Patent Submitted') }}</option>
                                        <option value="patent_granted" @selected($research->status === 'patent_granted')>{{ __('Patent Granted') }}</option>
                                    </select>
                                    <span style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;color:#94A3B8;">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </div>
                            </div>

                            {{-- Remarks --}}
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;letter-spacing:0.02em;">
                                    {{ __('Remarks / Notes') }}
                                    <span style="font-size:11px;font-weight:400;color:#94A3B8;margin-left:4px;">{{ __('(optional)') }}</span>
                                </label>
                                <textarea name="remarks" rows="3"
                                    placeholder="{{ __('Describe what has changed or been achieved...') }}"
                                    style="width:100%;padding:10px 12px;border:1.5px solid #E2E8F0;border-radius:8px;font-size:13px;color:#0F172A;background:#fff;font-family:inherit;resize:vertical;box-sizing:border-box;transition:border-color 0.15s;line-height:1.5;text-transform: uppercase"
                                    onfocus="this.style.borderColor='#1E3A8A';this.style.boxShadow='0 0 0 3px rgba(30,58,138,0.08)'"
                                    onblur="this.style.borderColor='#E2E8F0';this.style.boxShadow='none'"></textarea>
                            </div>

                            {{-- Supporting document --}}
                            <div>
                                <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:2px;letter-spacing:0.02em;">
                                    {{ __('Supporting Document') }}
                                    <span style="color:#DC2626;">*</span>
                                </label>
                                <p style="font-size:11px;color:#94A3B8;margin:0 0 10px;">{{ __('Upload proof supporting your new status (e.g. publication proof, conference certificate, patent receipt), or paste a public link.') }}</p>

                                <div x-data="researchShowDocumentUpload()">

                                    <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#94A3B8;margin:0 0 8px;">
                                        {{ __('Upload Method') }}
                                    </p>
                                    <div class="kmsar-tabs" style="margin-bottom: 1rem;">
                                        <button
                                            type="button"
                                            class="kmsar-tab"
                                            :class="{ 'active': uploadType === 'file' }"
                                            @click="uploadType = 'file'"
                                        >{{ __('Upload File') }}</button>
                                        <button
                                            type="button"
                                            class="kmsar-tab"
                                            :class="{ 'active': uploadType === 'link' }"
                                            @click="uploadType = 'link'"
                                        >{{ __('Add Link') }}</button>
                                    </div>

                                    {{-- File upload panel --}}
                                    <div x-show="uploadType==='file'">
                                        <div
                                            class="kmsar-dropzone"
                                            @click="$refs.fileInput.click()"
                                            @dragover.prevent
                                            @drop.prevent="handleDrop($event)"
                                        >
                                            <svg class="kmsar-dropzone-icon" xmlns="http://www.w3.org/2000/svg"
                                                fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                            </svg>
                                            <p class="kmsar-dropzone-title">
                                                <span>{{ __('Click to upload') }}</span> {{ __('or drag and drop') }}
                                            </p>
                                            <p class="kmsar-dropzone-hint">
                                                {{ __('PDF, Word, Excel, Image · Max 100MB · 2 files max') }}
                                            </p>
                                            <input
                                                type="file"
                                                x-ref="fileInput"
                                                name="files[]"
                                                multiple
                                                accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                                class="hidden"
                                                style="display:none;"
                                                @change="handleFileSelect($event)"
                                            >
                                            <p class="modal-file-name" style="font-size:12px;color:#475569;margin:10px 0 0;min-height:16px;"></p>
                                        </div>
                                    </div>

                                    {{-- Link panel --}}
                                    <div x-show="uploadType==='link'" x-cloak>
                                        <div style="border:1px solid #E2E8F0;border-radius:10px;padding:16px;background:#F8FAFC;">
                                            <div style="position:relative;">
                                                <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;">
                                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                                                    </svg>
                                                </span>
                                                <input type="url" name="external_link"
                                                    placeholder="https://drive.google.com/file/d/... or https://doi.org/..."
                                                    style="width:100%;padding:10px 12px 10px 34px;border:1.5px solid #E2E8F0;border-radius:8px;font-size:13px;font-family:inherit;background:#fff;color:#0F172A;box-sizing:border-box;transition:border-color 0.15s;"
                                                    onfocus="this.style.borderColor='#1E3A8A';this.style.boxShadow='0 0 0 3px rgba(30,58,138,0.08)'"
                                                    onblur="this.style.borderColor='#E2E8F0';this.style.boxShadow='none'">
                                            </div>
                                            <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
                                                <span style="padding:3px 10px;background:#EFF6FF;color:#1D4ED8;border-radius:6px;font-size:11px;font-weight:600;">Google Drive</span>
                                                <span style="padding:3px 10px;background:#EFF6FF;color:#1D4ED8;border-radius:6px;font-size:11px;font-weight:600;">OneDrive</span>
                                                <span style="padding:3px 10px;background:#EFF6FF;color:#1D4ED8;border-radius:6px;font-size:11px;font-weight:600;">DOI Link</span>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>

                        {{-- Footer --}}
                        <div style="height:1px;background:#F1F5F9;flex-shrink:0;"></div>
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 24px;flex-shrink:0;background:#FAFAFA;">
                            <button type="button" x-on:click="showUpdateProgress = false"
                                style="padding:9px 20px;background:#fff;border:1px solid #E2E8F0;color:#475569;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;transition:background 0.15s;"
                                onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='#fff'">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                style="padding:9px 22px;background:#1E3A8A;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:7px;transition:background 0.15s;"
                                onmouseover="this.style.background='#1e40af'" onmouseout="this.style.background='#1E3A8A'">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Submit Progress Update') }}
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <style>
            @media (max-width: 1024px) {
                .kmsar-review-grid {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>

        <div id="kmsar-preview-modal"
            style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:24px;box-sizing:border-box;">
            <div style="background:#fff;border-radius:12px;width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #E2E8F0;flex-shrink:0;">
                    <span id="kmsar-preview-modal-filename" style="font-size:13px;font-weight:600;color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:calc(100% - 48px);"></span>
                    <button type="button" onclick="kmsarClosePreviewModal()"
                        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;color:#64748B;border-radius:6px;cursor:pointer;flex-shrink:0;">
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
window.kmsarClosePreviewModal = function() {
    var modal = document.getElementById('kmsar-preview-modal');
    var iframe = document.getElementById('kmsar-preview-modal-iframe');
    if (!modal || !iframe) return;
    iframe.src = '';
    modal.style.display = 'none';
    document.body.style.overflow = '';
};
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') window.kmsarClosePreviewModal();
});
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('kmsar-preview-modal');
    if (modal) modal.addEventListener('click', function(e) {
        if (e.target === modal) window.kmsarClosePreviewModal();
    });
});
</script>
@endpush
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('researchShowDocumentUpload', () => ({
                uploadType: 'file',
                handleDrop(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const input = this.$refs.fileInput;
                    if (!input || !e.dataTransfer || !e.dataTransfer.files || !e.dataTransfer.files.length) {
                        return;
                    }
                    const files = e.dataTransfer.files;
                    if (files.length > 2) {
                        alert(@json(__('Maximum 2 files at a time.')));
                        input.value = '';
                        return;
                    }
                    const dt = new DataTransfer();
                    Array.from(files).forEach((f) => dt.items.add(f));
                    input.files = dt.files;
                    this.handleFileSelect({ target: input });
                },
                handleFileSelect(e) {
                    const input = e.target;
                    if (input.files.length > 2) {
                        alert(@json(__('Maximum 2 files at a time.')));
                        input.value = '';
                        return;
                    }
                    const root = input.closest('.kmsar-dropzone');
                    const disp = root ? root.querySelector('.modal-file-name') : null;
                    if (disp) {
                        if (input.files.length === 0) {
                            disp.textContent = '';
                        } else if (input.files.length === 1) {
                            disp.textContent = input.files[0].name;
                        } else {
                            disp.textContent = input.files.length + ' files selected';
                        }
                    }
                },
            }));
        });
    </script>
@endpush

@extends('layouts.app')

@section('title', __('Upload documents — Step 3'))

@section('navbar-context')
    {{ __('Faculty · Research registration') }}
@endsection

@section('content')
    @php
        $maxUploadFiles = $research->maxFileDocuments();
        $fileDocumentCount = $research->fileDocumentsCount();
        $remainingFileUploadSlots = $research->remainingFileUploadSlots();
    @endphp
    <div
        x-data="kmsarResearchDocumentsPage({{ $fileDocumentCount }}, {{ $maxUploadFiles }}, {{ (int) $research->documents->count() }})"
        x-init="@if (session('success')) $nextTick(() => document.getElementById('upload-success-alert')?.scrollIntoView({ behavior: 'smooth', block: 'start' })) @endif"
    >
        <x-page-header
            :title="$research->reference_number"
            :subtitle="($documentsOnlyMode ?? false)
                ? __('Manage supporting documents · registration details are locked')
                : __('Step 3 of 3 · Upload documents') . ' · ' . str($research->title)->limit(100)"
            :breadcrumb="[
                ['label' => __('My Research'), 'route' => 'research.index'],
                ['label' => $research->reference_number, 'route' => 'research.show', 'parameters' => [$research]],
                ['label' => __('Documents')],
            ]"
        >
            @if ($documentsOnlyMode ?? false)
                <x-button variant="outline" href="{{ route('research.show', $research) }}">{{ __('Back to research') }}</x-button>
            @else
                <x-button variant="outline" href="{{ route('research.wizard.authors', $research) }}">{{ __('Back') }}</x-button>
            @endif
            <x-button variant="primary" href="{{ route('research.show', $research) }}">{{ __('View Research Record') }}</x-button>
        </x-page-header>

        @if (session('success'))
            <x-alert type="success" :message="session('success')" class="mb-6" id="upload-success-alert" />
        @endif

        @if (session('warning'))
            <x-alert type="warning" :message="session('warning')" class="mb-6" />
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

        <div class="kmsar-alert kmsar-alert--info mb-6" role="status">
            <p class="text-sm mb-0 font-semibold text-slate-900">
                {{ __('File upload limit') }}
            </p>
            <p class="text-sm mb-0 mt-1">
                <span x-text="counterText">{{ $fileDocumentCount }} {{ __('of') }} {{ $maxUploadFiles }} {{ __('files uploaded') }} · {{ $remainingFileUploadSlots }} {{ __('remaining') }}</span>
            </p>
            <p class="kmsar-form-hint mt-1 mb-0">{{ __('External links are not counted toward the file limit.') }}</p>
        </div>

        @include('faculty.research.partials.registration-stepper', [
            'currentStep' => 3,
            'research' => $research,
            'step1Complete' => $step1Complete,
            'step2Complete' => $step2Complete,
            'documentsOnlyMode' => $documentsOnlyMode ?? false,
        ])

        @php
            $requirementMatrix = [
                ['status' => 'draft', 'status_label' => __('Draft / registration'), 'documents' => __('Abstract or Proposal Paper')],
                ['status' => 'research_registered', 'status_label' => __('Research registered'), 'documents' => __('Progress Report or Partial Data')],
                ['status' => 'completed_unpublished', 'status_label' => __('Done, not presented/published'), 'documents' => __('Full Paper / Manuscript')],
                ['status' => 'presented_internal', 'status_label' => __('Presented inside AUF'), 'documents' => __('Certificate + Conference Program')],
                ['status' => 'presented_external', 'status_label' => __('Presented outside AUF'), 'documents' => __('Certificate + Conference Program')],
                ['status' => 'published_non_indexed', 'status_label' => __('Published, not Scopus'), 'documents' => __('Full Published Article')],
                ['status' => 'published_scopus', 'status_label' => __('Scopus/WoS Indexed'), 'documents' => __('Published Article')],
                ['status' => 'patent_submitted', 'status_label' => __('Submitted to IPOPHL'), 'documents' => __('Acknowledgement Receipt')],
                ['status' => 'patent_granted', 'status_label' => __('Patent granted'), 'documents' => __('Patent Certificate')],
            ];
            $currentRequirement = collect($requirementMatrix)->firstWhere('status', $research->status);
        @endphp

        <div class="kmsar-alert kmsar-alert--info mb-6" role="status">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
            </svg>
            <div>
                <strong class="block mb-1">{{ __('Requirements') }}</strong>
                @if ($currentRequirement)
                    <p class="mb-0 text-sm">
                        {{ __('Progress status') }}:
                        <span class="font-semibold">{{ $currentRequirement['status_label'] }}</span>
                        — {{ __('upload') }}: <span class="font-semibold">{{ $currentRequirement['documents'] }}</span>
                    </p>
                @else
                    <p class="mb-0 text-sm">{{ __('Upload documents that match your declared progress status.') }}</p>
                @endif
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-semibold" style="color:#1E3A8A;">{{ __('View required documents by progress status') }}</summary>
                    <div class="kmsar-table-wrap overflow-x-auto mt-3">
                        <table class="kmsar-table w-full min-w-[36rem]">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Progress status') }}</th>
                                    <th scope="col">{{ __('Required documents') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requirementMatrix as $row)
                                    @php $isCurrent = $research->status === $row['status']; @endphp
                                    <tr @if($isCurrent) style="background:#E0F2FE;" @endif>
                                        <td class="align-top">
                                            <span style="font-weight: {{ $isCurrent ? '600' : '400' }}; color: #0F172A;">{{ $row['status_label'] }}</span>
                                            @if ($isCurrent)
                                                <span style="display:inline-block;margin-left:8px;font-size:11px;font-weight:600;color:#0369A1;white-space:nowrap;">← {{ __('Your current status') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $row['documents'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>

        <div class="kmsar-tabs mb-6" role="tablist" aria-label="{{ __('Document registration') }}">
            <button
                type="button"
                role="tab"
                class="kmsar-tab"
                :class="{ 'active': tab === 'upload' }"
                :aria-selected="tab === 'upload'"
                @click="tab = 'upload'"
            >{{ __('Upload documents') }}</button>
        </div>

        {{-- Upload documents --}}
        <div x-show="tab === 'upload'" class="space-y-6" role="tabpanel">
            <div class="kmsar-card kmsar-card--accent-primary">
                <div class="kmsar-card-header">
                    <h2 class="kmsar-card-title">{{ __('Upload documents') }}</h2>
                </div>
                <div class="kmsar-card-body space-y-6">
                    @if (session('success'))
                        <x-alert type="success" :message="session('success')" role="status" />
                    @endif

                    <div class="kmsar-alert kmsar-alert--info" role="status">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <div>
                            <strong class="block mb-1">{{ __('Required for your progress status') }}</strong>
                            @switch ($research->status)
                                @case('draft')
                                    {{ __('Your research is saved as a draft. Upload an abstract or proposal paper before submitting.') }}
                                    @break
                                @case('research_registered')
                                    {{ __('Your research is registered. Upload a progress report, partial data, or other supporting documents.') }}
                                    @break
                                @case('completed_unpublished')
                                    {{ __('Your work is complete but not yet published or presented. Upload the full paper or manuscript.') }}
                                    @break
                                @case('presented_internal')
                                    {{ __('You presented internally. Upload the certificate of presentation and the conference program.') }}
                                    @break
                                @case('presented_external')
                                    {{ __('You presented externally. Upload the certificate of presentation and the conference program.') }}
                                    @break
                                @case('published_non_indexed')
                                    {{ __('Your work is published (non-indexed). Upload the full published article.') }}
                                    @break
                                @case('published_scopus')
                                    {{ __('Your work is published in a Scopus/WoS Indexed venue. Upload the published article.') }}
                                    @break
                                @case('patent_submitted')
                                    {{ __('Your patent is submitted to IPOPHL. Upload the acknowledgement receipt.') }}
                                    @break
                                @case('patent_granted')
                                    {{ __('Your patent has been granted. Upload the patent certificate.') }}
                                    @break
                                @default
                                    {{ __('Upload documents that match your declared progress status. See the Requirements notice above for the full matrix.') }}
                            @endswitch
                        </div>
                    </div>

                    <form action="{{ route('documents.upload', $research) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        @include('faculty.research.partials.document-upload-fields', ['maxUploadFiles' => $maxUploadFiles])

                        {{-- Submit button --}}
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;">
                            <a href="{{ route('research.wizard.authors', $research) }}" class="kmsar-btn kmsar-btn--secondary">{{ __('Back') }}</a>
                            <button type="submit" class="kmsar-btn kmsar-btn--primary">
                                {{ __('Save Document') }}
                            </button>
                        </div>
                    </form>

                    @if ($research->documents->isNotEmpty())
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <h3 class="kmsar-h3 mb-0">{{ __('Uploaded files') }}</h3>
                                <p class="text-sm text-slate-600 mb-0">
                                    <span x-text="counterText">{{ $fileDocumentCount }} {{ __('of') }} {{ $maxUploadFiles }} {{ __('files uploaded') }} · {{ $remainingFileUploadSlots }} {{ __('remaining') }}</span>
                                </p>
                            </div>
                            <div class="space-y-0 divide-y divide-[var(--color-border)] border border-[var(--color-border)] rounded-lg overflow-hidden">
                                @foreach ($research->documents as $document)
                                    @php
                                        $sizeMb = $document->file_size_bytes > 0 ? round($document->file_size_bytes / 1048576, 2) : 0;
                                    @endphp
                                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 bg-[var(--color-surface)]">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-sm">{{ $document->original_filename }}</div>
                                            <div class="kmsar-table-cell-sub mt-0.5 text-xs">
                                                @if($document->external_link)
                                                    {{ __('Link') }}
                                                @else
                                                    {{ $sizeMb }} {{ __('MB') }}
                                                @endif
                                                · {{ $document->created_at?->format('M j, Y g:i a') ?? '—' }}
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
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
                                                <a href="{{ route('documents.preview', [$research, $document]) }}"
                                                    target="_blank"
                                                    class="kmsar-btn kmsar-btn--outline kmsar-btn--sm">
                                                    {{ __('Preview') }}
                                                </a>
                                                <a href="{{ route('documents.download', [$research, $document]) }}"
                                                    class="kmsar-btn kmsar-btn--secondary kmsar-btn--sm"
                                                    style="border: 1.5px solid var(--color-border) !important;">
                                                    {{ __('Download') }}
                                                </a>
                                                </div>
                                            @endif
                                            @if(\App\Support\ResearchStatus::canModifyDocuments((string) $research->status) && (int) $document->uploaded_by === (int) auth()->id())
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
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="kmsar-body mb-0" style="color: var(--color-text-muted);">{{ __('No documents uploaded yet.') }}</p>
                    @endif

                    {{-- Submit for review — shown at bottom of Documents tab --}}
                    <div style="margin-top:24px; padding-top:20px; border-top:1px solid #E2E8F0;">
                        @if (\App\Support\ResearchStatus::isPreSubmission((string) $research->status))
                            @can('submit', $research)
                                <form method="POST" action="{{ route('research.submit', $research) }}" x-data="{ submitting: false }" @submit="submitting = true">
                                    @csrf
                                    <button type="submit"
                                        class="kmsar-btn kmsar-btn--primary"
                                        style="width:100%;padding:12px;"
                                        :disabled="documentCount === 0 || submitting"
                                        :style="(documentCount === 0 || submitting) ? 'width:100%;padding:12px;opacity:0.5;cursor:not-allowed;' : 'width:100%;padding:12px;'">
                                        <span x-show="!submitting">
                                        @if ($research->registration_type === 'existing')
                                            {{ __('Register existing research') }}
                                        @else
                                            {{ __('Submit for initial dean review') }}
                                        @endif
                                        </span>
                                        <span x-show="submitting" x-cloak>{{ __('Submitting…') }}</span>
                                    </button>
                                    <p x-show="documentCount === 0"
                                       x-cloak
                                       style="text-align:center;color:#DC2626;font-size:13px;margin-top:8px;">
                                        {{ __('Please upload at least one document before submitting.') }}
                                    </p>
                                </form>
                            @endcan
                        @elseif (in_array($research->status, [\App\Support\ResearchStatus::INITIAL_REJECTED, \App\Support\ResearchStatus::FINAL_REJECTED], true))
                            @can('revise', $research)
                                <a href="{{ route('research.show', $research) }}"
                                   class="kmsar-btn kmsar-btn--primary"
                                   style="display:block;text-align:center;padding:12px;width:100%;box-sizing:border-box;">
                                    {{ __('Save changes & view research') }}
                                </a>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div id="kmsar-preview-modal"
            style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:24px;box-sizing:border-box;">
            <div style="background:#fff;border-radius:12px;width:100%;max-width:900px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">

                {{-- Modal header --}}
                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #E2E8F0;flex-shrink:0;">
                    <span id="kmsar-preview-modal-filename" style="font-size:13px;font-weight:600;color:#0F172A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:calc(100% - 48px);"></span>
                    <button type="button" onclick="kmsarClosePreviewModal()"
                        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border:none;background:transparent;color:#64748B;border-radius:6px;cursor:pointer;flex-shrink:0;"
                        onmouseover="this.style.background='#F1F5F9'" onmouseout="this.style.background='transparent'">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Modal body --}}
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
@endsection

@push('scripts-head')
@include('faculty.research.partials.document-upload-alpine')
@endpush

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

document.getElementById('kmsar-preview-modal')?.addEventListener('click', function(e) {
    if (e.target === this) window.kmsarClosePreviewModal();
});
</script>
@endpush

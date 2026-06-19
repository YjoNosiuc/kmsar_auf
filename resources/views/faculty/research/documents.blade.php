@extends('layouts.app')

@section('title', __('Upload documents — Step 3'))

@section('navbar-context')
    {{ __('Faculty · Research registration') }}
@endsection

@section('content')
    <div x-data="{ tab: 'upload' }">
        <x-page-header
            :title="$research->reference_number"
            :subtitle="__('Step 3 of 3 · Upload documents and finish registration') . ' · ' . str($research->title)->limit(100)"
            :breadcrumb="[
                ['label' => __('My Research'), 'route' => 'research.index'],
                ['label' => $research->reference_number, 'route' => 'research.show', 'parameters' => [$research]],
                ['label' => __('Documents')],
            ]"
        >
            <x-button variant="outline" href="{{ route('research.wizard.authors', $research) }}">{{ __('Back') }}</x-button>
            <x-button variant="primary" href="{{ route('research.show', $research) }}">{{ __('View Research Record') }}</x-button>
        </x-page-header>

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

        @include('faculty.research.partials.registration-stepper', ['currentStep' => 3, 'research' => $research])

        <div class="kmsar-tabs mb-6" role="tablist" aria-label="{{ __('Document registration') }}">
            <button
                type="button"
                role="tab"
                class="kmsar-tab"
                :class="{ 'active': tab === 'upload' }"
                :aria-selected="tab === 'upload'"
                @click="tab = 'upload'"
            >{{ __('Upload documents') }}</button>
            <button
                type="button"
                role="tab"
                class="kmsar-tab"
                :class="{ 'active': tab === 'requirements' }"
                :aria-selected="tab === 'requirements'"
                @click="tab = 'requirements'"
            >{{ __('Requirements') }}</button>
            <button
                type="button"
                role="tab"
                class="kmsar-tab"
                :class="{ 'active': tab === 'finish' }"
                :aria-selected="tab === 'finish'"
                @click="tab = 'finish'"
            >@if ($research->revision_count > 0){{ __('Finish') }}@else{{ __('Finish registration') }}@endif</button>
        </div>

        {{-- Tab 1: Upload documents --}}
        <div x-show="tab === 'upload'" class="space-y-6" role="tabpanel">
            <div class="kmsar-card kmsar-card--accent-primary">
                <div class="kmsar-card-header">
                    <h2 class="kmsar-card-title">{{ __('Upload documents') }}</h2>
                </div>
                <div class="kmsar-card-body space-y-6">
                    <div class="kmsar-alert kmsar-alert--info" role="status">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                        <div>
                            <strong class="block mb-1">{{ __('Required for your progress status') }}</strong>
                            @switch ($research->status)
                                @case('proposal')
                                    {{ __('You are in the proposal / abstract stage. Upload an abstract or proposal paper.') }}
                                    @break
                                @case('ongoing')
                                    {{ __('Your research is ongoing. Upload a progress report or partial data.') }}
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
                                    {{ __('Your work is published in a Scopus/ISI venue. Upload the published article.') }}
                                    @break
                                @case('patent_submitted')
                                    {{ __('Your patent is submitted to IPOPHL. Upload the acknowledgement receipt.') }}
                                    @break
                                @case('patent_granted')
                                    {{ __('Your patent has been granted. Upload the patent certificate.') }}
                                    @break
                                @default
                                    {{ __('Upload documents that match your declared progress status. See the Requirements tab for the full matrix.') }}
                            @endswitch
                        </div>
                    </div>

                    <form action="{{ route('documents.upload', $research) }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div x-data="{ uploadType: 'file' }">

                            {{-- Upload method: kmsar-tabs / kmsar-tab (matches page tab pattern) --}}
                            <div class="mb-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">
                                    {{ __('Upload method') }}
                                </p>
                                <div class="kmsar-tabs mb-4" role="tablist" aria-label="{{ __('Upload method') }}">
                                    <button
                                        type="button"
                                        role="tab"
                                        class="kmsar-tab"
                                        :class="{ 'active': uploadType === 'file' }"
                                        :aria-selected="uploadType === 'file'"
                                        @click="uploadType='file'"
                                    >{{ __('Upload File') }}</button>
                                    <button
                                        type="button"
                                        role="tab"
                                        class="kmsar-tab"
                                        :class="{ 'active': uploadType === 'link' }"
                                        :aria-selected="uploadType === 'link'"
                                        @click="uploadType='link'"
                                    >{{ __('Add Link') }}</button>
                                </div>
                            </div>

                            {{-- File upload panel --}}
                            <div x-show="uploadType==='file'">
                                <label
                                    class="kmsar-dropzone block max-w-full cursor-pointer"
                                    @dragover.prevent="$el.classList.add('kmsar-dropzone--drag')"
                                    @dragleave.prevent="$el.classList.remove('kmsar-dropzone--drag')"
                                    @drop.prevent="$el.classList.remove('kmsar-dropzone--drag')"
                                >
                                    <svg class="kmsar-dropzone-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    <p class="kmsar-dropzone-title"><span>{{ __('Choose files') }}</span> {{ __('or drag and drop') }}</p>
                                    <p class="kmsar-form-hint kmsar-dropzone-hint mt-0">{{ __('Maximum 2 files · PDF, Word, Excel, Image · Max 100MB each') }}</p>
                                    <input id="kmsar-document-file-input" type="file" name="files[]" multiple
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                        class="hidden"
                                        onchange="
                                            if(this.files.length > 2){
                                                alert('{{ __('You can only upload 2 files at a time.') }}');
                                                this.value='';
                                                if (typeof window.kmsarClearDocumentFilePreview === 'function') kmsarClearDocumentFilePreview(this);
                                                return;
                                            }
                                            const label = this.closest('label').querySelector('.file-name-display');
                                            if(label) {
                                                if(this.files.length === 0) label.textContent = '';
                                                else if(this.files.length === 1) label.textContent = this.files[0].name;
                                                else label.textContent = this.files.length + ' files selected';
                                            }
                                            if (typeof window.kmsarUpdateDocumentFilePreview === 'function') kmsarUpdateDocumentFilePreview(this);
                                        ">
                                    <p class="file-name-display text-sm text-slate-600 mt-2 min-h-[1rem]"></p>
                                </label>

                                <div id="kmsar-doc-file-preview-root" class="kmsar-doc-file-preview-root" style="display:none;margin-top:16px;border:1px solid #E2E8F0;border-radius:10px;padding:16px;background:#F8FAFC;box-sizing:border-box;" hidden>
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:12px;">
                                        <span style="font-size:13px;font-weight:600;color:#0F172A;">{{ __('Preview') }}</span>
                                        <button type="button" id="kmsar-doc-file-preview-dismiss" class="kmsar-doc-file-preview-dismiss" aria-label="{{ __('Clear preview') }}" title="{{ __('Clear preview') }}" style="flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;border:none;background:transparent;color:#64748B;border-radius:6px;cursor:pointer;transition:background 0.15s,color 0.15s;">
                                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <div id="kmsar-doc-file-preview-body" style="display:flex;flex-direction:column;gap:16px;"></div>
                                </div>
                            </div>

                            {{-- Link panel --}}
                            <div x-show="uploadType==='link'" x-cloak>
                                <div style="border:1px solid #E2E8F0;border-radius:10px;padding:20px;background:#F8FAFC;">
                                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                                        <div style="width:36px;height:36px;background:#EFF6FF;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                            <svg style="width:18px;height:18px;color:#1E3A8A;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p style="font-size:13px;font-weight:600;color:#0F172A;margin:0 0 2px;">{{ __('Add a document link') }}</p>
                                            <p style="font-size:12px;color:#94A3B8;margin:0;">{{ __('Google Drive, OneDrive, DOI, or any public URL') }}</p>
                                        </div>
                                    </div>

                                    <div style="position:relative;">
                                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#94A3B8;">
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

                                    <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#EFF6FF;color:#1D4ED8;border-radius:6px;font-size:11px;font-weight:600;">
                                            <svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                                            Google Drive
                                        </span>
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#EFF6FF;color:#1D4ED8;border-radius:6px;font-size:11px;font-weight:600;">
                                            <svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                                            OneDrive
                                        </span>
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#EFF6FF;color:#1D4ED8;border-radius:6px;font-size:11px;font-weight:600;">
                                            <svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/><path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/></svg>
                                            DOI Link
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Submit button --}}
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top: 1.5rem;">
                                <a href="{{ route('research.wizard.authors', $research) }}" class="kmsar-btn kmsar-btn--secondary">{{ __('Back') }}</a>
                                <button type="submit" class="kmsar-btn kmsar-btn--primary">
                                    {{ __('Save Document') }}
                                </button>
                            </div>

                        </div>
                    </form>

                    @if ($research->documents->isNotEmpty())
                        <div>
                            <h3 class="kmsar-h3 mb-3">{{ __('Uploaded files') }}</h3>
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
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="kmsar-body mb-0" style="color: var(--color-text-muted);">{{ __('No documents uploaded yet.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Tab 2: Requirements matrix --}}
        <div x-show="tab === 'requirements'" x-cloak class="space-y-6" style="display: none;" role="tabpanel">
            @php
                $requirementMatrix = [
                    ['status' => 'proposal', 'status_label' => __('Proposal / abstract stage'), 'documents' => __('Abstract or Proposal Paper')],
                    ['status' => 'ongoing', 'status_label' => __('Research in progress'), 'documents' => __('Progress Report or Partial Data')],
                    ['status' => 'completed_unpublished', 'status_label' => __('Done, not presented/published'), 'documents' => __('Full Paper / Manuscript')],
                    ['status' => 'presented_internal', 'status_label' => __('Presented inside AUF'), 'documents' => __('Certificate + Conference Program')],
                    ['status' => 'presented_external', 'status_label' => __('Presented outside AUF'), 'documents' => __('Certificate + Conference Program')],
                    ['status' => 'published_non_indexed', 'status_label' => __('Published, not Scopus'), 'documents' => __('Full Published Article')],
                    ['status' => 'published_scopus', 'status_label' => __('Scopus/ISI indexed'), 'documents' => __('Published Article')],
                    ['status' => 'patent_submitted', 'status_label' => __('Submitted to IPOPHL'), 'documents' => __('Acknowledgement Receipt')],
                    ['status' => 'patent_granted', 'status_label' => __('Patent granted'), 'documents' => __('Patent Certificate')],
                ];
            @endphp
            <div class="kmsar-card kmsar-card--accent-primary">
                <div class="kmsar-card-header">
                    <h2 class="kmsar-card-title">{{ __('Required documents by progress status') }}</h2>
                </div>
                <div class="kmsar-card-body">
                    <div class="kmsar-table-wrap overflow-x-auto">
                        <table class="kmsar-table w-full min-w-[36rem]">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Progress status') }}</th>
                                    <th scope="col">{{ __('Required documents') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($requirementMatrix as $row)
                                    @php
                                        $isCurrent = $research->status === $row['status'];
                                    @endphp
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
                </div>
            </div>
        </div>

        {{-- Tab 3: Finish registration --}}
        <div x-show="tab === 'finish'" x-cloak class="space-y-6" style="display: none;" role="tabpanel">
            <div class="kmsar-card kmsar-card--accent-primary">
                <div class="kmsar-card-header">
                    <h2 class="kmsar-card-title">
                        @if ($research->revision_count > 0)
                            {{ __('Changes saved') }}
                        @else
                            {{ __('Finish registration') }}
                        @endif
                    </h2>
                </div>
                <div class="kmsar-card-body" style="padding:0;">
                    @php
                        $detailsComplete = $research->title !== __('Untitled research');
                        $docCount = $research->documents->count();
                    @endphp
                    <div style="padding:24px;">
                        <div style="background:#F0FDF4;border:1px solid #A7F3D0;border-radius:10px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
                            <div style="width:40px;height:40px;background:#059669;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg style="width:20px;height:20px;color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <div style="font-size:14px;font-weight:600;color:#065F46;">{{ __('Research draft saved successfully') }}</div>
                                <div style="font-size:12px;color:#059669;margin-top:2px;">{{ __('Reference') }}: {{ $research->reference_number }}</div>
                            </div>
                        </div>

                        @if ($research->revision_count > 0)
                            <p class="kmsar-body" style="margin:0 0 24px;font-size:13px;color:#475569;line-height:1.6;">
                                {{ __('Your changes have been saved. Review your research and submit for dean review when ready.') }}
                            </p>
                        @else
                            <div style="margin-bottom:24px;">
                                <div style="font-size:13px;font-weight:600;color:#0F172A;margin-bottom:12px;">{{ __('Before submitting for review:') }}</div>
                                <div style="display:flex;flex-direction:column;gap:8px;">
                                    <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:#475569;">
                                        <span style="width:20px;height:20px;border-radius:50%;background:{{ $detailsComplete ? '#059669' : '#E2E8F0' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px;color:#fff;">{{ $detailsComplete ? '✓' : '!' }}</span>
                                        {{ __('Research details completed') }}
                                    </div>
                                    <div style="display:flex;align-items:center;gap:10px;font-size:13px;color:#475569;">
                                        <span style="width:20px;height:20px;border-radius:50%;background:{{ $docCount > 0 ? '#059669' : '#E2E8F0' }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px;color:#fff;">{{ $docCount > 0 ? '✓' : '!' }}</span>
                                        {{ __('Required document uploaded') }} ({{ $docCount }} {{ $docCount === 1 ? __('file') : __('files') }})
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;">
                            @if ($research->revision_count > 0)
                                <a href="{{ route('research.show', $research) }}"
                                   style="display:inline-block;padding:10px 24px;background:#1E3A8A;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;">
                                    {{ __('Save Changes & View Research') }}
                                </a>
                            @else
                                <form method="POST" action="{{ route('research.submit', $research) }}">
                                    @csrf
                                    <button type="submit" style="padding:10px 24px;background:#1E3A8A;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                                        {{ __('Submit for Dean Review') }} →
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('research.index') }}" style="padding:10px 20px;border:1px solid #CBD5E1;color:#475569;border-radius:8px;font-size:13px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;">
                                {{ __('Back to My Research') }}
                            </a>
                        </div>
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
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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

@push('scripts')
<script>
(function () {
    var kmsarBlobUrls = [];

    function kmsarRevokeBlobUrls() {
        kmsarBlobUrls.forEach(function (u) {
            try { URL.revokeObjectURL(u); } catch (e) {}
        });
        kmsarBlobUrls = [];
    }

    function kmsarFormatFileSize(bytes) {
        if (!bytes && bytes !== 0) return '—';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function kmsarFileTypeLabel(file) {
        if (file.type) return file.type;
        var n = file.name || '';
        var i = n.lastIndexOf('.');
        return i >= 0 ? n.slice(i + 1).toUpperCase() : @json(__('Unknown type'));
    }

    function kmsarGetPreviewKind(file) {
        var name = (file.name || '').toLowerCase();
        var m = file.type || '';
        if (m === 'application/pdf' || name.endsWith('.pdf')) return 'pdf';
        if (m.indexOf('image/') === 0 || /\.(jpe?g|png|gif|webp|bmp|svg)$/i.test(name)) return 'image';
        return 'other';
    }

    function kmsarSetPreviewRootVisible(root, visible) {
        if (!root) return;
        if (visible) {
            root.removeAttribute('hidden');
            root.style.display = 'block';
        } else {
            root.setAttribute('hidden', '');
            root.style.display = 'none';
        }
    }

    window.kmsarClearDocumentFilePreview = function (input) {
        kmsarRevokeBlobUrls();
        var root = document.getElementById('kmsar-doc-file-preview-root');
        var body = document.getElementById('kmsar-doc-file-preview-body');
        if (body) body.innerHTML = '';
        kmsarSetPreviewRootVisible(root, false);
        if (input) {
            input.value = '';
            var label = input.closest('label');
            if (label) {
                var disp = label.querySelector('.file-name-display');
                if (disp) disp.textContent = '';
            }
        }
    };

    window.kmsarUpdateDocumentFilePreview = function (input) {
        var root = document.getElementById('kmsar-doc-file-preview-root');
        var body = document.getElementById('kmsar-doc-file-preview-body');
        if (!root || !body) return;

        kmsarRevokeBlobUrls();
        body.innerHTML = '';

        if (!input || !input.files || input.files.length === 0) {
            kmsarSetPreviewRootVisible(root, false);
            return;
        }

        var cardShell = 'border:1px solid #E2E8F0;border-radius:8px;background:#fff;padding:12px;overflow:hidden;box-sizing:border-box;';

        for (var i = 0; i < input.files.length; i++) {
            (function (file) {
                var url = URL.createObjectURL(file);
                kmsarBlobUrls.push(url);
                var kind = kmsarGetPreviewKind(file);
                var wrap = document.createElement('div');
                wrap.style.cssText = cardShell;

                if (kind === 'pdf' || kind === 'image') {
                    var fn = document.createElement('p');
                    fn.style.cssText = 'font-size:12px;font-weight:600;color:#0F172A;margin:0 0 10px;word-break:break-all;';
                    fn.textContent = file.name;
                    wrap.appendChild(fn);
                }

                if (kind === 'pdf') {
                    var iframe = document.createElement('iframe');
                    iframe.src = url;
                    iframe.setAttribute('title', file.name);
                    iframe.style.cssText = 'width:100%;height:400px;border:none;border-radius:4px;display:block;';
                    wrap.appendChild(iframe);
                } else if (kind === 'image') {
                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = file.name;
                    img.style.cssText = 'max-width:100%;height:auto;display:block;border-radius:4px;';
                    wrap.appendChild(img);
                } else {
                    var row = document.createElement('div');
                    row.style.cssText = 'display:flex;align-items:flex-start;gap:12px;';
                    var iconBox = document.createElement('div');
                    iconBox.style.cssText = 'width:44px;height:44px;background:#EFF6FF;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;';
                    iconBox.innerHTML = '<svg style="width:22px;height:22px;color:#1E3A8A;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
                    var meta = document.createElement('div');
                    meta.style.cssText = 'min-width:0;flex:1;';
                    var tName = document.createElement('p');
                    tName.style.cssText = 'font-size:13px;font-weight:600;color:#0F172A;margin:0 0 4px;word-break:break-all;';
                    tName.textContent = file.name;
                    var tSize = document.createElement('p');
                    tSize.style.cssText = 'font-size:12px;color:#64748B;margin:0 0 4px;';
                    tSize.textContent = kmsarFormatFileSize(file.size);
                    var tType = document.createElement('p');
                    tType.style.cssText = 'font-size:12px;color:#94A3B8;margin:0;';
                    tType.textContent = kmsarFileTypeLabel(file);
                    meta.appendChild(tName);
                    meta.appendChild(tSize);
                    meta.appendChild(tType);
                    row.appendChild(iconBox);
                    row.appendChild(meta);
                    wrap.appendChild(row);
                }

                body.appendChild(wrap);
            })(input.files[i]);
        }

        kmsarSetPreviewRootVisible(root, true);
    };

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('kmsar-doc-file-preview-dismiss');
        var input = document.getElementById('kmsar-document-file-input');
        if (btn && input) {
            btn.addEventListener('mouseenter', function () { btn.style.background = '#F1F5F9'; btn.style.color = '#0F172A'; });
            btn.addEventListener('mouseleave', function () { btn.style.background = 'transparent'; btn.style.color = '#64748B'; });
            btn.addEventListener('click', function () {
                window.kmsarClearDocumentFilePreview(input);
            });
        }
    });
})();

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

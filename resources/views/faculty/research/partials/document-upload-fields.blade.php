{{-- Requires parent Alpine scope from kmsarDocumentUpload or kmsarResearchDocumentsPage --}}
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
            @click="uploadType = 'file'"
        >{{ __('Upload File') }}</button>
        <button
            type="button"
            role="tab"
            class="kmsar-tab"
            :class="{ 'active': uploadType === 'link' }"
            :aria-selected="uploadType === 'link'"
            @click="uploadType = 'link'"
        >{{ __('Add Link') }}</button>
    </div>
</div>

<div x-show="uploadType === 'file'">
    <template x-if="remainingSlots === 0">
        <p class="kmsar-form-hint mb-3">{{ __('You have reached the maximum of :max uploaded files for this research.', ['max' => $maxUploadFiles ?? 10]) }}</p>
    </template>

    <div
        class="kmsar-dropzone block max-w-full"
        :class="remainingSlots === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'"
        role="button"
        tabindex="0"
        @click="remainingSlots > 0 && $refs.fileInput.click()"
        @keydown.enter.prevent="remainingSlots > 0 && $refs.fileInput.click()"
        @keydown.space.prevent="remainingSlots > 0 && $refs.fileInput.click()"
        @dragover.prevent="remainingSlots > 0 && $el.classList.add('kmsar-dropzone--drag')"
        @dragleave.prevent="$el.classList.remove('kmsar-dropzone--drag')"
        @drop.prevent="handleDrop($event)"
    >
        <svg class="kmsar-dropzone-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>
        <p class="kmsar-dropzone-title"><span>{{ __('Choose files') }}</span> {{ __('or drag and drop') }}</p>
        <p class="kmsar-form-hint kmsar-dropzone-hint mt-0">
            {{ __('Maximum :max files per research · PDF, Word, Excel, Image · Max 100MB each', ['max' => $maxUploadFiles ?? 10]) }}
        </p>
        <p class="kmsar-form-hint mt-1 mb-0" x-text="counterText"></p>
        <input
            type="file"
            x-ref="fileInput"
            name="files[]"
            multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
            class="hidden"
            tabindex="-1"
            :disabled="remainingSlots === 0"
            @change="handleFileSelect($event)"
        >
    </div>

    <p class="kmsar-form-hint mt-2">{{ __('You may select up to all remaining file slots in one upload.') }}</p>

    <div x-show="selectedCount > 0" class="mt-4 border border-[#E2E8F0] rounded-lg p-4 bg-[#F8FAFC]">
        <div class="flex items-center justify-between gap-3 mb-3">
            <span class="text-sm font-semibold text-slate-900">{{ __('Ready to save') }} (<span x-text="selectedCount"></span>)</span>
            <button
                type="button"
                class="kmsar-btn kmsar-btn--outline kmsar-btn--sm"
                @click="clearSelected()"
            >{{ __('Clear all') }}</button>
        </div>

        <div class="flex flex-col gap-3 mb-4">
            <template x-for="item in selectedFiles" :key="item.uid">
                <div
                    class="flex items-center gap-3 p-3 border rounded-lg bg-white"
                    :class="activePreview?.uid === item.uid ? 'border-[#1E3A8A] ring-1 ring-[#1E3A8A]' : 'border-[#E2E8F0]'"
                >
                    <div class="w-10 h-10 rounded-lg bg-[#EFF6FF] flex items-center justify-center flex-shrink-0 overflow-hidden">
                        <img x-show="item.kind === 'image'" :src="item.previewUrl" :alt="item.name" class="w-full h-full object-cover">
                        <svg x-show="item.kind !== 'image'" class="w-5 h-5 text-[#1E3A8A]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 truncate" x-text="item.name"></p>
                        <p class="text-xs text-slate-500" x-text="item.sizeLabel"></p>
                    </div>
                    <button
                        type="button"
                        x-show="item.canPreview"
                        @click="previewFile(item)"
                        class="kmsar-btn kmsar-btn--outline kmsar-btn--sm"
                    >{{ __('View') }}</button>
                    <button
                        type="button"
                        @click="removeFile(item.uid)"
                        class="inline-flex items-center justify-center w-8 h-8 border border-red-200 bg-red-50 rounded-md text-red-600"
                        :aria-label="@json(__('Remove file'))"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>

        {{-- Inline preview — stays on this page, no new tab --}}
        <div x-show="activePreview" class="border border-[#E2E8F0] rounded-lg overflow-hidden bg-white">
            <div class="px-4 py-2 border-b border-[#E2E8F0] bg-[#F8FAFC]">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 m-0">{{ __('Preview') }}</p>
                <p class="text-sm font-semibold text-slate-900 m-0 truncate" x-text="activePreview?.name || ''"></p>
            </div>
            <div class="p-3 bg-[#F8FAFC]">
                <iframe
                    x-show="activePreview?.kind === 'pdf'"
                    :src="activePreview?.previewUrl || ''"
                    :title="activePreview?.name || ''"
                    class="w-full rounded-md border border-[#E2E8F0] bg-white"
                    style="height:420px;"
                ></iframe>
                <img
                    x-show="activePreview?.kind === 'image'"
                    :src="activePreview?.previewUrl || ''"
                    :alt="activePreview?.name || ''"
                    class="max-w-full h-auto mx-auto block rounded-md border border-[#E2E8F0] bg-white"
                >
                <p x-show="activePreview && !activePreview.canPreview" class="text-sm text-slate-500 m-0 px-2 py-4">
                    {{ __('Preview is not available for this file type. You can still save the document.') }}
                </p>
            </div>
        </div>
    </div>
</div>

<div x-show="uploadType === 'link'" x-cloak>
    <div class="border border-[#E2E8F0] rounded-lg p-5 bg-[#F8FAFC]">
        <div class="flex items-center gap-2.5 mb-3.5">
            <div class="w-9 h-9 bg-[#EFF6FF] rounded-lg flex items-center justify-center flex-shrink-0">
                <svg class="w-[18px] h-[18px] text-[#1E3A8A]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900 m-0">{{ __('Add a document link') }}</p>
                <p class="text-xs text-slate-400 m-0">{{ __('Google Drive, OneDrive, DOI, or any public URL') }}</p>
            </div>
        </div>
        <input type="text" name="external_link" class="kmsar-input w-full" placeholder="https://">
        <p class="kmsar-form-hint mt-2 mb-0">{{ __('External links are not counted toward the 10-file limit.') }}</p>
    </div>
</div>

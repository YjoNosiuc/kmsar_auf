<?php

namespace App\Http\Requests;

use App\Models\Research;
use App\Rules\ResearchExternalLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $links = $this->input('external_links');

        if (! is_array($links) && $this->filled('external_link')) {
            $links = [$this->input('external_link')];
        }

        if (! is_array($links)) {
            $links = [];
        }

        $this->merge([
            'external_links' => array_values(array_map(
                static fn ($link) => is_string($link) ? trim($link) : '',
                $links,
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Research $research */
        $research = $this->route('research');
        $maxFiles = $research->maxFileDocuments();
        $remainingSlots = $research->remainingFileUploadSlots();
        $maxLinks = (int) config('kmsar.max_research_external_links', 10);
        $maxUploadKb = (int) config('kmsar.max_upload_size_kb', 102400);

        return [
            'outcome_classifications' => ['required', 'array', 'min:1'],
            'outcome_classifications.*' => [
                'string',
                Rule::in(config('kmsar.outcome_classification_codes', [])),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'files' => ['required', 'array', 'min:1', 'max:'.$remainingSlots],
            'files.*' => ['file', 'max:'.$maxUploadKb, 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            'external_links' => ['nullable', 'array', 'max:'.$maxLinks],
            'external_links.*' => ['nullable', 'string', 'max:2048', new ResearchExternalLink],
            'external_link' => ['nullable', 'string', 'max:2048', new ResearchExternalLink],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxUploadKb = (int) config('kmsar.max_upload_size_kb', 102400);
        /** @var Research|null $research */
        $research = $this->route('research');
        $maxFiles = $research?->maxFileDocuments() ?? (int) config('kmsar.max_research_upload_files', 10);

        return [
            'outcome_classifications.required' => __('Select at least one outcome classification.'),
            'outcome_classifications.min' => __('Select at least one outcome classification.'),
            'files.required' => __('Please upload at least one supporting document.'),
            'files.min' => __('Please upload at least one supporting document.'),
            'files.max' => __('You may upload up to :remaining more file(s) for this research (maximum :max total).', [
                'remaining' => $this->route('research')?->remainingFileUploadSlots() ?? $maxFiles,
                'max' => $maxFiles,
            ]),
            'files.*.max' => __('Each file must be :size MB or smaller.', [
                'size' => (int) ($maxUploadKb / 1024),
            ]),
            'files.*.mimes' => __('Files must be PDF, Word, Excel, or image format.'),
            'external_links.max' => __('You may add up to :max links at once.', ['max' => config('kmsar.max_research_external_links', 10)]),
        ];
    }
}

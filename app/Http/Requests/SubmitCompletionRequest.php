<?php

namespace App\Http\Requests;

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
        $maxFiles = (int) config('kmsar.max_research_upload_files', 10);
        $maxLinks = (int) config('kmsar.max_research_external_links', 10);

        return [
            'outcome_classifications' => ['required', 'array', 'min:1'],
            'outcome_classifications.*' => [
                'string',
                Rule::in(config('kmsar.outcome_classification_codes', [])),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'external_links' => ['nullable', 'array', 'max:'.$maxLinks],
            'external_links.*' => ['nullable', 'string', 'max:2048', new ResearchExternalLink],
            'external_link' => ['nullable', 'string', 'max:2048', new ResearchExternalLink],
            'files' => ['nullable', 'array', 'max:'.$maxFiles],
            'files.*' => ['file', 'max:102400', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outcome_classifications.required' => __('Select at least one outcome classification.'),
            'outcome_classifications.min' => __('Select at least one outcome classification.'),
            'files.max' => __('You may upload up to :max files at once.', ['max' => config('kmsar.max_research_upload_files', 10)]),
            'files.*.max' => __('Each file must be 100 MB or smaller.'),
            'files.*.mimes' => __('Files must be PDF, Word, Excel, or image format.'),
            'external_links.max' => __('You may add up to :max links at once.', ['max' => config('kmsar.max_research_external_links', 10)]),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\College;
use App\Models\Document;
use App\Models\Program;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Notifications\ResearchProgressUpdated;
use App\Notifications\ResearchSubmissionConfirmed;
use App\Notifications\ResearchSubmitted;
use App\Services\ApprovalService;
use App\Services\FileValidationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResearchController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ApprovalService $approvalService,
        protected FileValidationService $fileValidation
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Research::class);

        $research = $this->approvalService->paginateResearchForUser($request->user());

        return view('faculty.research.index', [
            'research' => $research,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $this->authorize('create', Research::class);

        $research = $this->approvalService->createDraftAfterRegistrationType(
            $request->user(),
            'new'
        );

        return redirect()
            ->route('research.wizard.details', $research);
    }

    public function registrationDetails(Research $research): View
    {
        $this->authorize('update', $research);

        return view('faculty.research.details', [
            'research' => $research,
            'colleges' => College::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function saveRegistrationDetails(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('update', $research);

        $this->normalizeResearchFormRequest($request);

        $data = $request->validate($this->researchValidationRules());
        $data = $this->finalizeResearchPayload($data, $request);

        $this->approvalService->updateResearch($research, $data);

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.wizard.authors', $research)
            ->with('success', __('Details saved.'));
    }

    public function registrationAuthors(Research $research): View
    {
        $this->authorize('update', $research);

        $research->load([
            'researchAuthors' => fn ($q) => $q->orderBy('id'),
        ]);

        $externalPrimary = $research->researchAuthors
            ->where('is_primary', true)
            ->whereNull('user_id')
            ->first();

        $colleges = College::query()
            ->where('is_active', true)
            ->with(['programs' => fn ($q) => $q->where('is_active', true)->orderBy('code')])
            ->orderBy('code')
            ->get();

        $programsByCollege = Program::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->groupBy(fn (Program $p) => (string) $p->college_id)
            ->map(fn ($programs) => $programs->values());

        $oldPrimary = old('primary_author_type');
        if ($oldPrimary !== null) {
            $iAmPrimary = $oldPrimary === 'self';
            $primaryType = in_array($oldPrimary, ['student', 'employee'], true) ? $oldPrimary : 'student';
        } else {
            $iAmPrimary = $externalPrimary === null;
            $primaryType = ($externalPrimary && ($externalPrimary->author_type ?? 'student') === 'employee')
                ? 'employee'
                : 'student';
        }

        $coauthorsFromOld = old('authors');
        if (is_array($coauthorsFromOld)) {
            $coauthorsInitial = array_values($coauthorsFromOld);
        } else {
            $coauthorsNonPrimary = $research->researchAuthors->where('is_primary', false)->values();
            $coauthorsInitial = $coauthorsNonPrimary->map(function (ResearchAuthor $a) {
                return [
                    'isMe' => false,
                    'authorType' => $a->author_type ?? 'student',
                    'name' => $a->name,
                    'empNo' => $a->employee_number ?? '',
                    'institution' => $a->institution ?? '',
                    'collegeId' => $a->college_id ? (string) $a->college_id : '',
                    'programId' => $a->program_id ? (string) $a->program_id : '',
                    'affiliatedCollegeId' => $a->affiliated_college_id ? (string) $a->affiliated_college_id : '',
                    'email' => $a->email ?? '',
                ];
            })->all();

            if ($coauthorsInitial === []) {
                $coauthorsInitial = [[
                    'isMe' => false,
                    'authorType' => 'student',
                    'name' => '',
                    'empNo' => '',
                    'institution' => '',
                    'collegeId' => '',
                    'programId' => '',
                    'affiliatedCollegeId' => '',
                    'email' => '',
                ]];
            }
        }

        $selfAdded = collect($coauthorsInitial)->contains(fn ($row) => ! empty($row['isMe']));

        return view('faculty.research.authors', [
            'research' => $research,
            'colleges' => $colleges,
            'programsByCollege' => $programsByCollege,
            'externalPrimary' => $externalPrimary,
            'iAmPrimary' => $iAmPrimary,
            'primaryType' => $primaryType,
            'selfAdded' => $selfAdded,
            'coauthorsInitial' => $coauthorsInitial,
        ]);
    }

    public function saveRegistrationAuthors(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('update', $research);

        $authors = collect($request->input('authors', []))
            ->map(function (array $row) {
                foreach (['college_id', 'program_id', 'affiliated_college_id'] as $key) {
                    if (array_key_exists($key, $row) && $row[$key] === '') {
                        $row[$key] = null;
                    }
                }

                return $row;
            })
            ->all();
        $request->merge(['authors' => $authors]);

        foreach (['primary_author_college_id', 'primary_author_program_id'] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }

        $validated = $request->validate([
            'primary_author_type' => ['required', 'in:self,student,employee'],
            'primary_author_name' => ['required_unless:primary_author_type,self', 'nullable', 'string', 'max:150'],
            'primary_author_employee_number' => ['nullable', 'string', 'max:20'],
            'primary_author_email' => ['nullable', 'email', 'max:255'],
            'primary_author_college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'primary_author_program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'primary_author_institution' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'array'],
            'authors.*.name' => ['nullable', 'string', 'max:150'],
            'authors.*.author_type' => ['nullable', 'in:student,employee'],
            'authors.*.employee_number' => ['nullable', 'string', 'max:20'],
            'authors.*.email' => ['nullable', 'email', 'max:255'],
            'authors.*.college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'authors.*.program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'authors.*.affiliated_college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'authors.*.institution' => ['nullable', 'string', 'max:255'],
        ]);

        $coauthorRows = collect($validated['authors'] ?? [])
            ->filter(fn (array $row) => filled($row['name'] ?? null))
            ->values();

        DB::transaction(function () use ($research, $validated, $coauthorRows) {
            $research->researchAuthors()->where('is_primary', false)->delete();

            $type = $validated['primary_author_type'];

            if ($type === 'self') {
                $research->researchAuthors()
                    ->where('is_primary', true)
                    ->whereNull('user_id')
                    ->delete();
            } else {
                ResearchAuthor::query()->updateOrCreate(
                    [
                        'research_id' => $research->id,
                        'is_primary' => true,
                        'user_id' => null,
                    ],
                    [
                        'author_type' => $type,
                        'name' => $validated['primary_author_name'] ?? '',
                        'employee_number' => $validated['primary_author_employee_number'] ?? null,
                        'email' => $validated['primary_author_email'] ?? null,
                        'college_id' => $validated['primary_author_college_id'] ?? null,
                        'program_id' => $type === 'student' ? ($validated['primary_author_program_id'] ?? null) : null,
                        'affiliated_college_id' => null,
                        'institution' => $type === 'employee' ? ($validated['primary_author_institution'] ?? null) : null,
                        'college_text' => null,
                        'program' => null,
                        'is_primary' => true,
                        'can_edit' => false,
                    ]
                );
            }

            foreach ($coauthorRows as $row) {
                $linkedUserId = ResearchAuthor::resolveLinkedUserId(
                    $row['email'] ?? null,
                    $row['employee_number'] ?? null,
                );

                ResearchAuthor::query()->create([
                    'research_id' => $research->id,
                    'user_id' => $linkedUserId,
                    'author_type' => $row['author_type'] ?? 'student',
                    'name' => $row['name'],
                    'employee_number' => $row['employee_number'] ?? null,
                    'email' => $row['email'] ?? null,
                    'college_id' => $row['college_id'] ?? null,
                    'program_id' => $row['program_id'] ?? null,
                    'affiliated_college_id' => $row['affiliated_college_id'] ?? null,
                    'institution' => $row['institution'] ?? null,
                    'college_text' => null,
                    'program' => null,
                    'is_primary' => false,
                    'can_edit' => ResearchAuthor::canEditForUserId($linkedUserId),
                ]);
            }
        });

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.wizard.documents', $research)
            ->with('success', __('Co-authors saved.'));
    }

    public function registrationDocuments(Research $research): View
    {
        $this->authorize('update', $research);

        $research->load(['documents']);

        return view('faculty.research.documents', [
            'research' => $research,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Research::class);

        $this->normalizeResearchFormRequest($request);

        $data = $request->validate($this->researchValidationRules());
        $data = $this->finalizeResearchPayload($data, $request);

        if ($this->approvalService->duplicateTitleExists((string) $data['title'])) {
            return redirect()
                ->back()
                ->withInput()
                ->with('warning', ApprovalService::DUPLICATE_TITLE_MESSAGE);
        }

        $research = $this->approvalService->createResearch($request->user(), $data);

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.show', $research)
            ->with('success', __('Research record saved.'));
    }

    public function show(Research $research): View
    {
        $this->authorize('view', $research);

        $research->load([
            'primaryAuthor.college',
            'motherCollege',
            'documents',
            'approvals' => fn ($q) => $q->orderBy('created_at'),
            'approvals.approver',
            'researchAuthors.college',
            'researchAuthors.program',
        ]);

        return view('faculty.research.show', [
            'research' => $research,
        ]);
    }

    public function edit(Research $research): View
    {
        $this->authorize('update', $research);

        $research->loadMissing(['motherCollege', 'primaryAuthor', 'researchAuthors']);

        return view('faculty.research.edit', [
            'research' => $research,
            'colleges' => College::query()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('update', $research);

        $this->normalizeResearchFormRequest($request);

        $data = $request->validate($this->researchValidationRules());
        $data = $this->finalizeResearchPayload($data, $request);

        $previousMotherCollegeId = $research->mother_college_id;

        $this->approvalService->updateResearch($research, $data);

        $research->refresh();

        $this->forgetResearchDashboardCaches($research, $previousMotherCollegeId);

        return redirect()
            ->route('research.show', $research)
            ->with('success', __('Research record updated.'));
    }

    public function submit(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('submit', $research);

        $this->approvalService->submit($research, $request->user());

        $research->refresh();

        $research->submitted_at = now();
        $research->save();

        $dean = User::whereHas('roles', function ($q) {
            $q->where('name', 'college_dean');
        })
            ->where('college_id', $research->mother_college_id)
            ->first();

        if ($dean) {
            $dean->notify(new ResearchSubmitted($research));
        }

        $research->primaryAuthor?->notify(new ResearchSubmissionConfirmed($research));

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.show', $research)
            ->with('success', __('Research submitted for dean review.'));
    }

    public function revise(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('revise', $research);

        $this->approvalService->resubmit($research, $request->user());

        $research->refresh();

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.edit', $research)
            ->with('success', __('Research returned to draft. Update the record and submit again when ready.'));
    }

    public function destroy(Research $research): RedirectResponse
    {
        if ($research->approval_stage !== 'draft') {
            abort(403, 'Only draft stage research can be deleted.');
        }

        if ($research->primary_author_id !== auth()->id()) {
            abort(403, 'You are not authorized to delete this research.');
        }

        $research->delete();

        return redirect()->route('research.index')
            ->with('success', 'Research record deleted successfully.');
    }

    public function updateProgress(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('updateProgress', $research);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                'ongoing', 'completed_unpublished', 'presented_internal',
                'presented_external', 'published_non_indexed', 'published_scopus',
                'patent_submitted', 'patent_granted',
            ])],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'external_link' => ['nullable', 'string', 'max:2048'],
        ]);

        $hasLink = $request->filled('external_link');
        $hasFiles = $request->hasFile('files');

        if ($hasLink && $hasFiles) {
            return back()->withErrors(['files' => __('Please choose either a file upload or a link, not both.')]);
        }

        if (! $hasLink && ! $hasFiles) {
            return back()->withErrors(['files' => __('Please upload supporting file(s) or paste a link.')]);
        }

        $docSummary = '';

        if ($hasLink) {
            $request->validate(['external_link' => ['required', 'url', 'max:2048']]);

            Document::create([
                'research_id' => $research->id,
                'uploaded_by' => $request->user()->id,
                'original_filename' => $request->input('external_link'),
                'stored_filename' => null,
                'disk_path' => null,
                'external_link' => $request->input('external_link'),
                'mime_type' => 'text/uri-list',
                'file_size_bytes' => 0,
                'research_status_at_upload' => $validated['status'],
                'version' => ((int) $research->documents()->max('version')) + 1,
            ]);

            $docSummary = $request->input('external_link');
        } else {
            $request->validate([
                'files' => ['required', 'array', 'max:2'],
                'files.*' => ['file', 'max:102400', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
            ]);

            $names = [];
            foreach ($request->file('files') as $index => $file) {
                $this->persistProgressUploadedFile(
                    $research,
                    $request->user(),
                    $file,
                    $validated['status'],
                    'files.'.$index
                );
                $names[] = $file->getClientOriginalName();
            }
            $docSummary = implode(', ', $names);
        }

        $research->update([
            'status' => $validated['status'],
            'approval_stage' => 'dean_review',
        ]);

        $remarksText = ($validated['remarks'] ?? '')
            .' [New status: '.$validated['status'].'] [Document: '.$docSummary.']';

        Approval::query()->create([
            'research_id' => $research->id,
            'approver_id' => $request->user()->id,
            'stage' => 'faculty',
            'action' => 'progress_update',
            'remarks' => $remarksText,
            'acted_at' => now(),
        ]);

        $research->refresh();

        $dean = User::whereHas('roles', function ($q) {
            $q->where('name', 'college_dean');
        })
            ->where('college_id', $research->mother_college_id)
            ->first();

        if ($dean) {
            $dean->notify(new ResearchProgressUpdated($research));
        }

        $this->forgetResearchDashboardCaches($research);

        // SendNotificationJob::dispatch($research, 'progress_updated');

        return redirect()->route('research.show', $research)
            ->with('success', __('Progress updated and document uploaded. Your Dean has been notified for re-endorsement.'));
    }

    private function persistProgressUploadedFile(
        Research $research,
        User $user,
        UploadedFile $file,
        string $newStatus,
        string $attribute
    ): void {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $this->fileValidation->validateMime($file, $extension, $attribute);

        $uuid = (string) Str::uuid();
        $storedBasename = $uuid.'.'.$extension;
        $collegeId = (int) $research->mother_college_id;
        $relativePath = 'research_files/'.$collegeId.'/'.$research->id.'/'.$storedBasename;

        $disk = $this->researchAppDisk();

        try {
            $disk->put($relativePath, $file->get());

            Document::create([
                'research_id' => $research->id,
                'uploaded_by' => $user->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => $storedBasename,
                'disk_path' => $relativePath,
                'external_link' => null,
                'mime_type' => $mimeType,
                'file_size_bytes' => $file->getSize(),
                'research_status_at_upload' => $newStatus,
                'version' => ((int) $research->documents()->max('version')) + 1,
            ]);
        } catch (\Throwable $e) {
            if ($disk->exists($relativePath)) {
                $disk->delete($relativePath);
            }
            throw $e;
        }
    }

    /**
     * Files live under storage/app/research_files/... (same as FileController).
     */
    private function researchAppDisk(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => true,
        ]);
    }

    public function allResearch(Request $request): View
    {
        $this->authorize('viewAny', Research::class);

        $research = $this->approvalService->paginateAllResearch(
            perPage: 20,
            college: $request->input('college'),
            stage: $request->input('stage'),
        );

        $colleges = \App\Models\College::where('is_active', true)->orderBy('code')->get();

        return view('ovpri.research.index', [
            'research' => $research,
            'colleges' => $colleges,
        ]);
    }

    private function normalizeSdgTags(Request $request): void
    {
        $raw = $request->input('sdg_tags');

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (is_array($raw)) {
            $request->merge([
                'sdg_tags' => array_values(array_map('intval', $raw)),
            ]);
        } else {
            $request->merge(['sdg_tags' => []]);
        }
    }

    private function normalizeResearchFormRequest(Request $request): void
    {
        $this->normalizeSdgTags($request);

        $otherColleges = $request->input('other_college_id');
        if (! is_array($otherColleges)) {
            $request->merge([
                'other_college_id' => $otherColleges === '' || $otherColleges === null ? [] : [(int) $otherColleges],
            ]);
        }

        $expected = $request->input('expected_output');
        if (! is_array($expected)) {
            $request->merge(['expected_output' => []]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function finalizeResearchPayload(array $data, Request $request): array
    {
        $data['sdg_tags'] = array_values(array_map('intval', $data['sdg_tags'] ?? []));
        $data['expected_output'] = array_values(array_unique($data['expected_output'] ?? []));
        $data['expected_output_other'] = in_array('other', $data['expected_output'], true)
            ? ($data['expected_output_other'] ?? null)
            : null;

        $motherCollegeId = (int) ($data['mother_college_id'] ?? 0);
        $otherCollegeIds = array_values(array_unique(array_map('intval', $data['other_college_id'] ?? [])));
        $otherCollegeIds = array_values(array_filter(
            $otherCollegeIds,
            fn (int $id) => $id > 0 && $id !== $motherCollegeId,
        ));
        $data['other_college_id'] = $otherCollegeIds === [] ? null : $otherCollegeIds;

        return $data;
    }

    private function forgetResearchDashboardCaches(Research $research, ?int $previousMotherCollegeId = null): void
    {
        Cache::forget('ovpri_stats_'.now()->format('Y-m-d-H'));
        Cache::forget('admin_monthly_stats_'.now()->format('Y-m'));
        Cache::forget('sdg_counts');

        $collegeIds = array_unique(array_filter([
            $research->mother_college_id,
            $previousMotherCollegeId,
        ]));

        foreach ($collegeIds as $collegeId) {
            foreach ($this->deanUserIdsForCollege((int) $collegeId) as $id) {
                Cache::forget('dean_stats_'.$id.'_'.now()->format('Y-m-d'));
            }
        }
    }

    /**
     * @return list<int>
     */
    private function deanUserIdsForCollege(int $collegeId): array
    {
        return User::query()
            ->where('college_id', $collegeId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['college_dean', 'unit_head']))
            ->pluck('id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function researchValidationRules(): array
    {
        return [
            'registration_type' => ['required', 'in:new,update'],
            'title' => ['required', 'string'],
            'mother_college_id' => ['required', 'exists:colleges,id'],
            'other_college_id' => ['nullable', 'array'],
            'other_college_id.*' => ['integer', 'exists:colleges,id'],
            'research_classification' => [
                'required',
                'string',
                'max:60',
                Rule::in([
                    'self_funded',
                    'internally_funded',
                    'externally_funded',
                    'thesis',
                    'thesis_dissertation',
                    'collaboration',
                    'other',
                ]),
            ],
            'funding_agency' => ['nullable', 'string', 'max:100'],
            'sdg_tags' => ['required', 'array', 'min:1'],
            'sdg_tags.*' => ['integer', 'between:1,17'],
            'expected_output' => ['required', 'array', 'min:1'],
            'expected_output.*' => ['in:publication,patent,policy_brief,other'],
            'expected_output_other' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => in_array('other', request()->input('expected_output', []), true)),
            ],
            'start_date' => ['required', 'date'],
            'estimated_completion_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', 'max:40'],
        ];
    }
}

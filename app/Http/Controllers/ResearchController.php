<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitCompletionRequest;
use App\Models\Approval;
use App\Models\College;
use App\Models\Document;
use App\Models\OutcomeClassification;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Notifications\ResearchProgressUpdated;
use App\Services\ApprovalService;
use App\Services\DashboardCacheService;
use App\Services\FileValidationService;
use App\Rules\ResearchExternalLink;
use App\Support\ResearchDeanRouting;
use App\Support\ResearchStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:40'],
            'review_cycle' => ['nullable', 'string', Rule::in(['initial', 'final'])],
        ]);

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === ''
            || ResearchStatus::isBlockedWorkflowFilter($status)
            || ! array_key_exists($status, ResearchStatus::facultyFilterOptions())) {
            $filters['status'] = '';
        } else {
            $filters['status'] = $status;
        }

        $research = $this->approvalService->paginateResearchForUser(
            $request->user(),
            15,
            $filters
        );

        return view('faculty.research.index', [
            'research' => $research,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'review_cycle' => $filters['review_cycle'] ?? '',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Research::class);

        return view('faculty.research.create');
    }

    public function beginRegistration(Request $request): RedirectResponse
    {
        $this->authorize('create', Research::class);

        $validated = $request->validate([
            'registration_type' => ['required', 'string', Rule::in(config('kmsar.registration_types', []))],
        ]);

        $research = $this->approvalService->findOrCreateShellDraft(
            $request->user(),
            $validated['registration_type']
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
            'step1Complete' => $this->wizardStep1Complete($research),
            'step2Complete' => $this->wizardStep2Complete($research),
        ]);
    }

    public function saveRegistrationDetails(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('update', $research);

        $this->normalizeResearchFormRequest($request);

        if ($request->boolean('save_as_draft')) {
            $data = $request->validate($this->draftRegistrationValidationRules());
            $data = $this->finalizeResearchPayload($data, $request);

            if (ResearchStatus::isPreSubmission((string) $research->status)) {
                $research->update(['registration_type' => $data['registration_type']]);
            }

            $this->approvalService->updateResearchDraft($research, $data);
            $this->forgetResearchDashboardCaches($research);

            return redirect()
                ->route('research.index')
                ->with('success', __('Draft saved. You can continue registration anytime from My Research.'));
        }

        $data = $request->validate($this->researchValidationRules());
        $data = $this->finalizeResearchPayload($data, $request);

        if (ResearchStatus::isPreSubmission((string) $research->status)) {
            $research->update(['registration_type' => $data['registration_type']]);
        }

        $this->approvalService->updateResearch($research, $data);

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.wizard.authors', $research)
            ->with('success', __('Details saved.'));
    }

    public function registrationAuthors(Research $research): View|RedirectResponse
    {
        $this->authorize('update', $research);

        if (! $this->wizardStep1Complete($research)) {
            return redirect()
                ->route('research.wizard.details', $research)
                ->with('warning', __('Please complete Step 1 before proceeding to Authors.'));
        }

        $me = request()->user()->loadMissing(['college', 'program', 'roles']);
        $meData = $this->authorUserData($me);

        $existingPrimary = $research->researchAuthors()
            ->where('is_primary', true)
            ->with(['user.college', 'user.program', 'user.roles'])
            ->first();

        $existingCoAuthors = $research->researchAuthors()
            ->where('is_primary', false)
            ->whereNotNull('user_id')
            ->with(['user.college', 'user.program', 'user.roles'])
            ->orderBy('id')
            ->get();

        $primaryUserId = old(
            'primary_author_user_id',
            $existingPrimary?->user_id ?? $research->primary_author_id
        );

        $primaryUser = $primaryUserId
            ? User::query()->with(['college', 'program', 'roles'])->find($primaryUserId)
            : null;
        $primaryData = $primaryUser ? $this->authorUserData($primaryUser) : null;

        $oldCoAuthors = old('coauthors');
        if (is_array($oldCoAuthors)) {
            $oldUsers = User::query()
                ->with(['college', 'program', 'roles'])
                ->whereIn('id', collect($oldCoAuthors)->pluck('user_id')->filter()->all())
                ->get()
                ->keyBy('id');

            $coAuthorsData = collect($oldCoAuthors)
                ->map(function (array $row) use ($oldUsers) {
                    $user = $oldUsers->get((int) ($row['user_id'] ?? 0));
                    if (! $user) {
                        return null;
                    }

                    return array_merge($this->authorUserData($user), [
                        'user_id' => $user->id,
                        'can_edit' => (bool) ($row['can_edit'] ?? false),
                    ]);
                })
                ->filter()
                ->values()
                ->all();
        } else {
            $coAuthorsData = $existingCoAuthors
                ->filter(fn (ResearchAuthor $author) => $author->user !== null)
                ->map(fn (ResearchAuthor $author) => array_merge(
                    $this->authorUserData($author->user),
                    [
                        'user_id' => $author->user_id,
                        'can_edit' => (bool) $author->can_edit,
                    ]
                ))
                ->values()
                ->all();
        }

        return view('faculty.research.authors', [
            'research' => $research,
            'meData' => $meData,
            'existingPrimary' => $existingPrimary,
            'existingCoAuthors' => $existingCoAuthors,
            'primaryData' => $primaryData,
            'coAuthorsData' => $coAuthorsData,
            'step1Complete' => $this->wizardStep1Complete($research),
            'step2Complete' => $this->wizardStep2Complete($research),
        ]);
    }

    public function saveRegistrationAuthors(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('update', $research);

        $validated = $request->validate([
            'primary_author_user_id' => ['required', 'integer', 'exists:users,id'],
            'coauthors' => ['nullable', 'array'],
            'coauthors.*.user_id' => [
                'required',
                'integer',
                'distinct',
                'exists:users,id',
                'different:primary_author_user_id',
            ],
            'coauthors.*.can_edit' => ['nullable', 'boolean'],
        ]);

        $primaryUserId = (int) $validated['primary_author_user_id'];
        $coAuthorRows = collect($validated['coauthors'] ?? []);

        if ($coAuthorRows->pluck('user_id')->map(fn ($id) => (int) $id)->contains($primaryUserId)) {
            return back()
                ->withInput()
                ->withErrors(['coauthors' => __('Primary author cannot also be a co-author.')]);
        }

        DB::transaction(function () use ($research, $validated, $primaryUserId, $coAuthorRows) {
            $userIds = $coAuthorRows->pluck('user_id')->map(fn ($id) => (int) $id)
                ->prepend($primaryUserId)
                ->unique()
                ->all();
            $users = User::query()
                ->with(['college', 'program'])
                ->whereIn('id', $userIds)
                ->get()
                ->keyBy('id');

            $primaryUser = $users->get($primaryUserId);
            abort_unless($primaryUser instanceof User, 422);

            $research->researchAuthors()->delete();

            ResearchAuthor::query()->create(
                $this->researchAuthorData($research, $primaryUser, true, true)
            );

            $research->update(['primary_author_id' => $primaryUser->id]);

            foreach ($coAuthorRows as $row) {
                $coAuthor = $users->get((int) $row['user_id']);
                if (! $coAuthor instanceof User) {
                    continue;
                }

                ResearchAuthor::query()->create(
                    $this->researchAuthorData(
                        $research,
                        $coAuthor,
                        false,
                        (bool) ($row['can_edit'] ?? true),
                    )
                );
            }
        });

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.wizard.documents', $research)
            ->with('success', __('Authors saved.'));
    }

    public function registrationDocuments(Research $research): View|RedirectResponse
    {
        $this->authorize('manageRegistrationDocuments', $research);

        $documentsOnlyMode = ResearchStatus::isDocumentsEditable((string) $research->status);

        if (! $documentsOnlyMode) {
            if (! $this->wizardStep1Complete($research)) {
                return redirect()
                    ->route('research.wizard.details', $research)
                    ->with('warning', __('Please complete Step 1 before proceeding.'));
            }

            if (! $this->wizardStep2Complete($research)) {
                return redirect()
                    ->route('research.wizard.authors', $research)
                    ->with('warning', __('Please select a primary author before proceeding to Documents.'));
            }
        }

        $research->load(['documents']);

        return view('faculty.research.documents', [
            'research' => $research,
            'step1Complete' => $documentsOnlyMode || $this->wizardStep1Complete($research),
            'step2Complete' => $documentsOnlyMode || $this->wizardStep2Complete($research),
            'documentsOnlyMode' => $documentsOnlyMode,
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
            'primaryAuthor.program',
            'motherCollege',
            'documents',
            'outcomeClassifications',
            'approvals' => fn ($q) => $q->orderBy('created_at'),
            'approvals.approver',
            'researchAuthors.college',
        ]);

        return view('faculty.research.show', [
            'research' => $research,
            'outcomeClassifications' => OutcomeClassification::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function edit(Research $research): View
    {
        $this->authorize('update', $research);

        $research->loadMissing(['motherCollege', 'primaryAuthor', 'researchAuthors']);

        $selectedOtherColleges = is_array($research->other_college_id)
            ? $research->other_college_id
            : (is_string($research->other_college_id)
                ? json_decode($research->other_college_id, true) ?? []
                : []);

        return view('faculty.research.edit', [
            'research' => $research,
            'colleges' => College::query()->where('is_active', true)->orderBy('code')->get(),
            'selectedOtherColleges' => array_values(array_map('intval', $selectedOtherColleges)),
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
        if (! ResearchStatus::isPreSubmission((string) $research->status)) {
            return redirect()
                ->route('research.show', $research)
                ->with('info', __('This research has already been submitted.'));
        }

        $this->authorize('submit', $research);

        $this->approvalService->submit($research, $request->user());

        $research->refresh();

        $this->forgetResearchDashboardCaches($research);

        if ($research->registration_type === 'existing') {
            return redirect()
                ->route('research.show', $research)
                ->with('success', __('Existing research registered. Your record is now research registered.'));
        }

        return redirect()
            ->route('research.show', $research)
            ->with('success', __('Research submitted for dean review.'));
    }

    public function revise(Request $request, Research $research): RedirectResponse
    {
        if ($research->status === ResearchStatus::INITIAL_REJECTED) {
            $this->authorize('resubmitInitial', $research);
            $this->approvalService->resubmitInitial($research, $request->user());

            $message = __('Your research has been resubmitted for initial dean review. You can edit registration details in the wizard if needed.');
        } elseif ($research->status === ResearchStatus::FINAL_REJECTED) {
            abort(403, __('Use the outcome update form to revise classifications and resubmit for final review.'));
        } else {
            abort(403);
        }

        $research->refresh();

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('research.show', $research)
            ->with('info', $message);
    }

    public function destroy(Research $research): RedirectResponse
    {
        if (! ResearchStatus::isPreSubmission((string) $research->status)) {
            abort(403, __('Only draft stage research can be deleted.'));
        }

        if ($research->primary_author_id !== auth()->id()) {
            abort(403, 'You are not authorized to delete this research.');
        }

        $research->delete();

        return redirect()->route('research.index')
            ->with('success', 'Research record deleted successfully.');
    }

    public function updateProgress(SubmitCompletionRequest $request, Research $research): RedirectResponse
    {
        $isFinalResubmit = $research->status === ResearchStatus::FINAL_REJECTED;

        if ($isFinalResubmit) {
            $this->authorize('resubmitFinal', $research);
        } else {
            $this->authorize('submitCompletion', $research);
        }

        $validated = $request->validated();

        $links = ResearchExternalLink::normalizeList($validated['external_links'] ?? []);

        if ($links === [] && $request->filled('external_link')) {
            $legacy = ResearchExternalLink::normalize((string) $request->input('external_link'));
            if ($legacy !== null) {
                $links = [$legacy];
            }
        }

        $hasFiles = $request->hasFile('files') && count($request->file('files')) > 0;

        if (! $hasFiles) {
            return back()
                ->withInput()
                ->withErrors(['proof' => __('Please upload at least one supporting document.')]);
        }

        $nextVersion = ((int) $research->documents()->max('version')) + 1;

        foreach ($links as $link) {
            Document::create([
                'research_id' => $research->id,
                'uploaded_by' => $request->user()->id,
                'original_filename' => $link,
                'stored_filename' => null,
                'disk_path' => null,
                'external_link' => $link,
                'mime_type' => 'text/uri-list',
                'file_size_bytes' => 0,
                'research_status_at_upload' => $research->status,
                'version' => $nextVersion++,
            ]);
        }

        if ($hasFiles) {
            foreach ($request->file('files') as $index => $file) {
                $this->persistProgressUploadedFile(
                    $research,
                    $request->user(),
                    $file,
                    (string) $research->status,
                    'files.'.$index
                );
            }
        }

        if ($isFinalResubmit) {
            $this->approvalService->resubmitFinal(
                $research,
                $request->user(),
                $validated['outcome_classifications'],
                $validated['remarks'] ?? null,
            );
            $successMessage = __('Your outcome submission has been resubmitted for final dean review.');
        } else {
            $this->approvalService->submitCompletion(
                $research,
                $request->user(),
                $validated['outcome_classifications'],
                $validated['remarks'] ?? null,
            );
            $successMessage = __('Research completion submitted. Your Dean has been notified for final review.');
        }

        $research->refresh();

        foreach (ResearchDeanRouting::deanUsersFor($research) as $dean) {
            $dean->notify(new ResearchProgressUpdated($research));
        }

        $this->forgetResearchDashboardCaches($research);

        return redirect()->route('research.show', $research)
            ->with('success', $successMessage);
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
            status: $request->input('status'),
            reviewCycle: $request->input('review_cycle'),
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
        $data['agenda_themes'] = array_values(array_unique(array_filter(
            array_map('strval', $data['agenda_themes'] ?? []),
        )));
        $data['research_classification_other'] = ($data['research_classification'] ?? '') === 'other'
            ? ($data['research_classification_other'] ?? null)
            : null;

        return $data;
    }

    private function forgetResearchDashboardCaches(Research $research, ?int $previousMotherCollegeId = null): void
    {
        unset($research, $previousMotherCollegeId);

        DashboardCacheService::flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function draftRegistrationValidationRules(): array
    {
        return [
            'registration_type' => ['required', 'in:new,existing'],
            'title' => ['required', 'string', 'max:500'],
            'mother_college_id' => ['nullable', 'exists:colleges,id'],
            'other_college_id' => ['nullable', 'array'],
            'other_college_id.*' => ['integer', 'exists:colleges,id'],
            'research_classification' => [
                'nullable',
                'string',
                'max:60',
                Rule::in(array_keys(config('kmsar.research_classifications', []))),
            ],
            'research_classification_other' => ['nullable', 'string', 'max:500'],
            'funding_agency' => ['nullable', 'string', 'max:100'],
            'agenda_themes' => ['nullable', 'array'],
            'agenda_themes.*' => ['string', Rule::in(array_keys(config('kmsar.agenda_themes', [])))],
            'sdg_tags' => ['nullable', 'array'],
            'sdg_tags.*' => ['integer', 'between:1,17'],
            'expected_output' => ['nullable', 'array'],
            'expected_output.*' => ['in:publication,patent,policy_brief,other'],
            'expected_output_other' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
            'estimated_completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function researchValidationRules(): array
    {
        return [
            'registration_type' => ['required', 'in:new,existing'],
            'title' => ['required', 'string'],
            'mother_college_id' => ['required', 'exists:colleges,id'],
            'other_college_id' => ['nullable', 'array'],
            'other_college_id.*' => ['integer', 'exists:colleges,id'],
            'research_classification' => [
                'required',
                'string',
                'max:60',
                Rule::in(array_keys(config('kmsar.research_classifications', []))),
            ],
            'research_classification_other' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () => request()->input('research_classification') === 'other'),
            ],
            'funding_agency' => ['nullable', 'string', 'max:100'],
            'agenda_themes' => ['nullable', 'array'],
            'agenda_themes.*' => ['string', Rule::in(array_keys(config('kmsar.agenda_themes', [])))],
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
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function wizardStep1Complete(Research $research): bool
    {
        return ! empty($research->title)
            && ! empty($research->research_classification)
            && ! empty($research->sdg_tags)
            && ! empty($research->start_date)
            && filled($research->registration_type);
    }

    private function wizardStep2Complete(Research $research): bool
    {
        return $research->researchAuthors()->where('is_primary', true)->exists();
    }

    private function authorUserData(User $user): array
    {
        $user->loadMissing(['college', 'program', 'roles']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'employee_number' => $user->employee_number,
            'college' => $user->college?->name ?? '—',
            'college_code' => $user->college?->code ?? '—',
            'program' => $user->program?->name ?? $user->office ?? '—',
            'role' => $user->getRoleNames()->first() ?? '—',
            'user_type' => $user->user_type ?? '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function researchAuthorData(
        Research $research,
        User $user,
        bool $isPrimary,
        bool $canEdit,
    ): array {
        $user->loadMissing(['college', 'program']);

        return [
            'research_id' => $research->id,
            'user_id' => $user->id,
            'author_type' => 'internal',
            'employee_number' => $user->employee_number,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'suffix' => $user->suffix,
            'college_id' => $user->college_id,
            'college_text' => $user->college?->name,
            'program_id' => $user->program_id,
            'program' => $user->program?->name ?? $user->office,
            'affiliated_college_id' => null,
            'institution' => null,
            'email' => $user->email,
            'is_primary' => $isPrimary,
            'can_edit' => $canEdit,
        ];
    }
}

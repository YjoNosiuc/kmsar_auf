<?php

namespace App\Services;

use App\Mail\ResearchEndorsedOvpriMail;
use App\Mail\ResearchSubmittedFacultyMail;
use App\Notifications\ResearchResubmitted;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\OutcomeClassification;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Notifications\ResearchEndorsed;
use App\Notifications\ResearchEndorsedToOvpri;
use App\Notifications\ResearchReturned;
use App\Notifications\ResearchReturnedToDean;
use App\Notifications\ResearchSubmissionConfirmed;
use App\Notifications\ResearchSubmitted;
use App\Support\ResearchDeanRouting;
use App\Support\ResearchStatus;
use App\Support\SafeMail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public const DUPLICATE_TITLE_MESSAGE = 'There is already an existing research with a similar title. Please check your research list before registering a new one.';

    /**
     * @return Collection<int, College>
     */
    public function activeCollegesOrdered(): Collection
    {
        return College::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{search?: string|null, status?: string|null, review_cycle?: string|null}  $filters
     */
    public function paginateResearchForUser(User $user, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Research::query()
            ->with(['motherCollege', 'primaryAuthor', 'outcomeClassifications'])
            ->latest();

        if ($user->hasRole('registrar')) {
            $query->where('status', ResearchStatus::RESEARCH_ACCEPTED);
        } elseif ($user->can('research.view_all')) {
            // university-wide
        } elseif ($user->can('research.view_college')) {
            $query->where('mother_college_id', $user->college_id);
        } elseif ($user->can('research.view_own')) {
            $query->where(function ($q) use ($user) {
                $q->where('primary_author_id', $user->id)
                    ->orWhereHas('researchAuthors', fn ($qq) => $qq->matchingUser($user));
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('reference_number', 'like', $like);
            });
        }

        // Hide empty draft shells; titled drafts remain visible to the author only.
        $query->where(function ($q) use ($user) {
            $q->where('status', '!=', ResearchStatus::DRAFT)
                ->orWhere(function ($visible) use ($user) {
                    $visible->where('status', ResearchStatus::DRAFT)
                        ->where('primary_author_id', $user->id)
                        ->whereNotNull('title')
                        ->where('title', '!=', '');
                });
        });

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && ! ResearchStatus::isBlockedWorkflowFilter($status)) {
            $query->where('status', $status);
        }

        $cycle = trim((string) ($filters['review_cycle'] ?? ''));
        if ($cycle === ResearchStatus::REVIEW_CYCLE_INITIAL) {
            $query->whereIn('status', ResearchStatus::initialReviewStatuses());
        } elseif ($cycle === ResearchStatus::REVIEW_CYCLE_FINAL) {
            $query->whereIn('status', ResearchStatus::finalReviewStatuses());
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function paginateAllResearch(
        int $perPage = 20,
        ?string $college = null,
        ?string $status = null,
        ?string $reviewCycle = null,
    ): LengthAwarePaginator {
        $institutionalStatus = $status !== null && ! ResearchStatus::isFacultyOnly($status)
            ? $status
            : null;

        return Research::query()
            ->excludeFacultyOnly()
            ->with(['motherCollege', 'primaryAuthor', 'outcomeClassifications'])
            ->when($college, fn ($q) => $q->where('mother_college_id', $college))
            ->when($reviewCycle === ResearchStatus::REVIEW_CYCLE_INITIAL, fn ($q) => $q->whereIn('status', ResearchStatus::initialReviewStatuses()))
            ->when($reviewCycle === ResearchStatus::REVIEW_CYCLE_FINAL, fn ($q) => $q->whereIn('status', ResearchStatus::finalReviewStatuses()))
            ->when($institutionalStatus, fn ($q) => $q->where('status', $institutionalStatus))
            ->when(! $institutionalStatus && ! $reviewCycle, fn ($q) => $q->where('status', ResearchStatus::RESEARCH_ACCEPTED))
            ->latest()
            ->paginate($perPage);
    }

    public function duplicateTitleExists(string $title, ?int $excludeResearchId = null): bool
    {
        $normalizedTitle = strtolower(trim($title));

        if ($normalizedTitle === '') {
            return false;
        }

        $query = Research::query()
            ->whereRaw('LOWER(title) = ?', [$normalizedTitle]);

        if ($excludeResearchId !== null) {
            $query->where('id', '!=', $excludeResearchId);
        }

        return $query->exists();
    }

    public function findOrCreateShellDraft(User $user, string $registrationType): Research
    {
        $existing = Research::query()
            ->where('primary_author_id', $user->id)
            ->where('status', ResearchStatus::DRAFT)
            ->where('registration_type', $registrationType)
            ->where(function ($query): void {
                $query->whereNull('title')->orWhere('title', '');
            })
            ->whereDoesntHave('documents')
            ->latest('updated_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->createDraftAfterRegistrationType($user, $registrationType);
    }

    public function createDraftAfterRegistrationType(User $user, string $registrationType): Research
    {
        if (! in_array($registrationType, config('kmsar.registration_types', []), true)) {
            throw ValidationException::withMessages([
                'registration_type' => [__('Invalid registration type.')],
            ]);
        }

        $collegeId = $user->college_id;

        if ($collegeId === null) {
            $collegeId = College::query()->where('is_active', true)->orderBy('code')->value('id');
        }

        if ($collegeId === null) {
            throw ValidationException::withMessages([
                'registration_type' => [__('No active college is configured. Contact the administrator.')],
            ]);
        }

        return DB::transaction(function () use ($user, $registrationType, $collegeId) {
            $referenceNumber = $this->allocateReferenceNumber((int) $collegeId);

            return Research::query()->create([
                'reference_number' => $referenceNumber,
                'registration_type' => $registrationType,
                'title' => '',
                'primary_author_id' => $user->id,
                'mother_college_id' => $collegeId,
                'research_classification' => 'other',
                'funding_agency' => null,
                'sdg_tags' => [],
                'expected_output' => ['publication'],
                'expected_output_other' => null,
                'start_date' => now()->toDateString(),
                'estimated_completion_date' => now()->addYear()->toDateString(),
                'status' => ResearchStatus::DRAFT,
                'revision_count' => 0,
                'final_review_count' => 0,
                'is_scopus_indexed' => false,
            ]);
        });
    }

    /**
     * @return list<array{name: string, employee_number: string, college_id: string}>
     */
    public function defaultAuthorRowsForResearch(Research $research): array
    {
        $rows = $research->researchAuthors()
            ->where('is_primary', false)
            ->orderBy('id')
            ->get()
            ->map(fn (ResearchAuthor $a) => [
                'name' => $a->name,
                'employee_number' => $a->employee_number ?? '',
                'college_id' => $a->college_id ? (string) $a->college_id : '',
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return [['name' => '', 'employee_number' => '', 'college_id' => '']];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, employee_number?: string|null, college_id?: int|string|null}>  $rows
     */
    public function syncCoAuthors(Research $research, array $rows): void
    {
        DB::transaction(function () use ($research, $rows) {
            $research->researchAuthors()->where('is_primary', false)->delete();

            foreach ($rows as $row) {
                $linkedUserId = ResearchAuthor::resolveLinkedUserId(
                    $row['email'] ?? null,
                    $row['employee_number'] ?? null,
                );

                ResearchAuthor::query()->create([
                    'research_id' => $research->id,
                    'user_id' => $linkedUserId,
                    'employee_number' => $row['employee_number'] ?? null,
                    'email' => $row['email'] ?? null,
                    'name' => $row['name'],
                    'college_id' => ! empty($row['college_id']) ? (int) $row['college_id'] : null,
                    'is_primary' => false,
                    'can_edit' => ResearchAuthor::canEditForUserId($linkedUserId),
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createResearch(User $user, array $data): Research
    {
        if ($this->duplicateTitleExists((string) $data['title'])) {
            throw ValidationException::withMessages([
                'title' => [self::DUPLICATE_TITLE_MESSAGE],
            ]);
        }

        return DB::transaction(function () use ($user, $data) {
            $referenceNumber = $this->allocateReferenceNumber((int) $data['mother_college_id']);

            return Research::query()->create([
                'reference_number' => $referenceNumber,
                'registration_type' => $data['registration_type'],
                'title' => $data['title'],
                'primary_author_id' => $user->id,
                'mother_college_id' => $data['mother_college_id'],
                'other_college_id' => $this->normalizeOtherCollegeIds(
                    $data['other_college_id'] ?? null,
                    (int) $data['mother_college_id'],
                ),
                'research_classification' => $data['research_classification'],
                'research_classification_other' => $data['research_classification_other'] ?? null,
                'funding_agency' => $data['funding_agency'] ?? null,
                'sdg_tags' => $data['sdg_tags'] ?? [],
                'agenda_themes' => $data['agenda_themes'] ?? [],
                'expected_output' => $data['expected_output'],
                'expected_output_other' => $data['expected_output_other'] ?? null,
                'start_date' => $data['start_date'],
                'estimated_completion_date' => $data['estimated_completion_date'],
                'status' => ResearchStatus::DRAFT,
                'revision_count' => 0,
                'final_review_count' => 0,
                'is_scopus_indexed' => false,
            ]);
        });
    }

    public function updateResearchDraft(Research $research, array $data): void
    {
        if (! ResearchStatus::isFullyEditable((string) $research->status)) {
            throw ValidationException::withMessages([
                'status' => [__('Registration details cannot be edited at this stage.')],
            ]);
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => [__('Title is required to save a draft.')],
            ]);
        }

        if ($this->duplicateTitleExists($title, $research->id)) {
            throw ValidationException::withMessages([
                'title' => [self::DUPLICATE_TITLE_MESSAGE],
            ]);
        }

        $updates = [
            'title' => $title,
            'status' => ResearchStatus::DRAFT,
            'registration_type' => $data['registration_type'] ?? $research->registration_type,
        ];

        if (filled($data['mother_college_id'] ?? null)) {
            $updates['mother_college_id'] = (int) $data['mother_college_id'];
        }

        if (array_key_exists('other_college_id', $data)) {
            $updates['other_college_id'] = $this->normalizeOtherCollegeIds(
                $data['other_college_id'] ?? null,
                (int) ($updates['mother_college_id'] ?? $research->mother_college_id),
            );
        }

        foreach ([
            'research_classification',
            'research_classification_other',
            'funding_agency',
            'expected_output_other',
            'start_date',
            'estimated_completion_date',
        ] as $field) {
            if (array_key_exists($field, $data) && filled($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('sdg_tags', $data)) {
            $updates['sdg_tags'] = $data['sdg_tags'] ?? [];
        }

        if (array_key_exists('agenda_themes', $data)) {
            $updates['agenda_themes'] = $data['agenda_themes'] ?? [];
        }

        if (array_key_exists('expected_output', $data) && is_array($data['expected_output']) && $data['expected_output'] !== []) {
            $updates['expected_output'] = $data['expected_output'];
        }

        $research->update($updates);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateResearch(Research $research, array $data): void
    {
        if (! ResearchStatus::isFullyEditable((string) $research->status)) {
            throw ValidationException::withMessages([
                'status' => [__('Registration details cannot be edited at this stage.')],
            ]);
        }

        if ($this->duplicateTitleExists((string) $data['title'], $research->id)) {
            throw ValidationException::withMessages([
                'title' => [self::DUPLICATE_TITLE_MESSAGE],
            ]);
        }

        $research->update([
            'title' => $data['title'],
            'mother_college_id' => $data['mother_college_id'],
            'other_college_id' => $this->normalizeOtherCollegeIds(
                $data['other_college_id'] ?? null,
                (int) $data['mother_college_id'],
            ),
            'research_classification' => $data['research_classification'],
            'research_classification_other' => $data['research_classification_other'] ?? null,
            'funding_agency' => $data['funding_agency'] ?? null,
            'sdg_tags' => $data['sdg_tags'] ?? [],
            'agenda_themes' => $data['agenda_themes'] ?? [],
            'expected_output' => $data['expected_output'],
            'expected_output_other' => $data['expected_output_other'] ?? null,
            'start_date' => $data['start_date'],
            'estimated_completion_date' => $data['estimated_completion_date'],
        ]);
    }

    /**
     * @param  list<string>  $codes
     */
    public function syncOutcomeClassifications(Research $research, array $codes): void
    {
        $ids = OutcomeClassification::query()
            ->whereIn('code', $codes)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (count($ids) !== count(array_unique($codes))) {
            throw ValidationException::withMessages([
                'outcome_classifications' => [__('One or more outcome classifications are invalid.')],
            ]);
        }

        $research->outcomeClassifications()->sync($ids);
    }

    public function submit(Research $research, User $actor): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if (! ResearchStatus::isPreSubmission($locked->status)) {
                throw ValidationException::withMessages([
                    'status' => [__('Research must be in draft stage to submit.')],
                ]);
            }

            if (! $locked->documents()->exists()) {
                throw ValidationException::withMessages([
                    'documents' => [__('At least one document is required before submission.')],
                ]);
            }

            $oldStatus = $locked->status;

            if ($locked->registration_type === 'existing') {
                $now = now();
                $locked->update([
                    'status' => ResearchStatus::RESEARCH_REGISTERED,
                    'submitted_at' => $now,
                    'research_registered_at' => $now,
                ]);

                $this->writeAuditLog($actor, $locked, 'research.submitted', [
                    'status' => $oldStatus,
                ], [
                    'status' => ResearchStatus::RESEARCH_REGISTERED,
                    'registration_type' => 'existing',
                    'review_cycle' => null,
                ]);

                return;
            }

            $locked->update([
                'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
                'submitted_at' => now(),
            ]);

            $this->writeAuditLog($actor, $locked, 'research.submitted', [
                'status' => $oldStatus,
            ], [
                'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
                'registration_type' => 'new',
                'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
            ]);
        });

        $fresh = Research::query()
            ->with(['primaryAuthor', 'researchAuthors'])
            ->findOrFail($researchId);

        if ($fresh->registration_type === 'existing') {
            $fresh->primaryAuthor?->notify(new ResearchSubmissionConfirmed($fresh));

            return;
        }

        foreach (ResearchDeanRouting::deanUsersFor($fresh) as $dean) {
            $dean->notify(new ResearchSubmitted($fresh));
        }

        $fresh->primaryAuthor?->notify(new ResearchSubmissionConfirmed($fresh));

        $delay = 0;
        $fresh->researchAuthors
            ->where('is_primary', false)
            ->filter(fn ($author) => filled($author->email))
            ->each(function ($author) use ($fresh, &$delay) {
                SafeMail::send(
                    $author->email,
                    new ResearchSubmittedFacultyMail($fresh),
                    $delay
                );
                $delay += 2;
            });
    }

    public function endorse(Research $research, User $dean, ?string $remarks = null): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $dean, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);
            $cycle = ResearchStatus::reviewCycle($locked->status);

            if ($locked->status === ResearchStatus::INITIAL_DEAN_REVIEW) {
                $this->assertDeanMayActOnResearch($dean, $locked);
                $this->assertDeanPermission($dean);

                $this->createApproval($locked, $dean, 'dean', ResearchStatus::REVIEW_CYCLE_INITIAL, 'endorsed', $remarks);

                $oldStatus = $locked->status;
                $locked->update(['status' => ResearchStatus::INITIAL_OVPRI_REVIEW]);

                $this->writeAuditLog($dean, $locked, 'approval.initial.endorsed', [
                    'status' => $oldStatus,
                ], [
                    'status' => ResearchStatus::INITIAL_OVPRI_REVIEW,
                    'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
                ]);

                return;
            }

            if ($locked->status === ResearchStatus::FINAL_DEAN_REVIEW) {
                $this->assertDeanMayActOnResearch($dean, $locked);
                $this->assertDeanPermission($dean);

                $this->createApproval(
                    $locked,
                    $dean,
                    'dean',
                    ResearchStatus::REVIEW_CYCLE_FINAL,
                    'endorsed',
                    $remarks,
                    (int) $locked->final_review_count,
                );

                $oldStatus = $locked->status;
                $locked->update(['status' => ResearchStatus::FINAL_OVPRI_REVIEW]);

                $this->writeAuditLog($dean, $locked, 'approval.final.endorsed', [
                    'status' => $oldStatus,
                ], [
                    'status' => ResearchStatus::FINAL_OVPRI_REVIEW,
                    'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
                    'final_review_count' => $locked->final_review_count,
                ]);

                return;
            }

            throw ValidationException::withMessages([
                'status' => [__('Research must be awaiting dean review to endorse.')],
            ]);
        });

        $fresh = Research::query()->with(['primaryAuthor', 'motherCollege'])->findOrFail($researchId);
        $fresh->primaryAuthor?->notify(new ResearchEndorsed($fresh));

        $delay = 0;
        User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['ovpri_admin', 'cdaic_admin']))
            ->get()
            ->each(function (User $admin) use ($fresh, &$delay) {
                $admin->notify(new ResearchEndorsedToOvpri($fresh));

                if (filled($admin->email)) {
                    SafeMail::send($admin->email, new ResearchEndorsedOvpriMail($fresh, $admin), $delay);
                    $delay += 2;
                }
            });
    }

    public function approve(Research $research, User $ovpri, ?string $remarks = null): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $ovpri, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if (! $ovpri->can('approval.approve')) {
                throw ValidationException::withMessages([
                    'user' => [__('You are not allowed to approve research at this stage.')],
                ]);
            }

            if ($locked->status === ResearchStatus::INITIAL_OVPRI_REVIEW) {
                $this->createApproval($locked, $ovpri, 'ovpri', ResearchStatus::REVIEW_CYCLE_INITIAL, 'approved', $remarks);

                $now = now();
                $oldStatus = $locked->status;
                $locked->update([
                    'status' => ResearchStatus::RESEARCH_REGISTERED,
                    'research_registered_at' => $now,
                ]);

                $this->writeAuditLog($ovpri, $locked, 'approval.initial.approved', [
                    'status' => $oldStatus,
                ], [
                    'status' => ResearchStatus::RESEARCH_REGISTERED,
                    'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
                    'research_registered_at' => $now->toIso8601String(),
                ]);

                return;
            }

            if ($locked->status === ResearchStatus::FINAL_OVPRI_REVIEW) {
                $this->createApproval(
                    $locked,
                    $ovpri,
                    'ovpri',
                    ResearchStatus::REVIEW_CYCLE_FINAL,
                    'approved',
                    $remarks,
                    (int) $locked->final_review_count,
                );

                $now = now();
                $oldStatus = $locked->status;
                $locked->update([
                    'status' => ResearchStatus::RESEARCH_ACCEPTED,
                    'research_accepted_at' => $now,
                ]);

                $this->writeAuditLog($ovpri, $locked, 'approval.final.approved', [
                    'status' => $oldStatus,
                ], [
                    'status' => ResearchStatus::RESEARCH_ACCEPTED,
                    'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
                    'final_review_count' => $locked->final_review_count,
                    'research_accepted_at' => $now->toIso8601String(),
                ]);

                return;
            }

            throw ValidationException::withMessages([
                'status' => [__('Research must be awaiting OVPRI review to approve.')],
            ]);
        });

    }

    public function return(Research $research, User $actor, string $remarks, string $stage = 'dean'): void
    {
        $this->sendBack($research, $actor, $remarks, $stage, 'returned');
    }

    public function resubmitInitial(Research $research, User $actor): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->status !== ResearchStatus::INITIAL_REJECTED) {
                throw ValidationException::withMessages([
                    'status' => [__('Only initial-review returned research can be resubmitted from registration.')],
                ]);
            }

            $oldStatus = $locked->status;
            $locked->update(['status' => ResearchStatus::INITIAL_DEAN_REVIEW]);

            $this->writeAuditLog($actor, $locked, 'research.initial.resubmitted', [
                'status' => $oldStatus,
            ], [
                'status' => ResearchStatus::INITIAL_DEAN_REVIEW,
                'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
            ]);
        });

        $this->notifyDeansOfResubmit($researchId);
    }

    public function resubmitFinal(Research $research, User $actor, array $classificationCodes, ?string $remarks = null): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor, $classificationCodes, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->status !== ResearchStatus::FINAL_REJECTED) {
                throw ValidationException::withMessages([
                    'status' => [__('Only final-review returned research can be resubmitted for outcome review.')],
                ]);
            }

            $this->advanceCompletionToFinalReview(
                $locked,
                $actor,
                $classificationCodes,
                ResearchStatus::FINAL_REJECTED,
                $remarks,
                incrementFinalReviewCount: false,
                completionAuditAction: 'research.final.resubmitted',
            );
        });

        $this->notifyDeansOfResubmit($researchId);
    }

    /**
     * @param  list<string>  $classificationCodes
     */
    public function submitCompletion(Research $research, User $actor, array $classificationCodes, ?string $remarks = null): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor, $classificationCodes, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if (! ResearchStatus::canSubmitCompletion($locked->status)) {
                throw ValidationException::withMessages([
                    'status' => [__('Completion can only be submitted from registered or accepted research.')],
                ]);
            }

            $this->advanceCompletionToFinalReview(
                $locked,
                $actor,
                $classificationCodes,
                (string) $locked->status,
                $remarks,
                incrementFinalReviewCount: true,
                completionAuditAction: 'research.completion_submitted',
            );
        });

    }

    /**
     * Sync outcomes, persist research_completed (audit-visible), then advance to final_dean_review.
     *
     * @param  list<string>  $classificationCodes
     */
    private function advanceCompletionToFinalReview(
        Research $locked,
        User $actor,
        array $classificationCodes,
        string $sourceStatus,
        ?string $remarks,
        bool $incrementFinalReviewCount,
        string $completionAuditAction,
    ): void {
        ResearchStatus::assertTransition($sourceStatus, ResearchStatus::RESEARCH_COMPLETED);

        $this->syncOutcomeClassifications($locked, $classificationCodes);

        $countBefore = (int) $locked->final_review_count;
        $iteration = $incrementFinalReviewCount ? $countBefore + 1 : $countBefore;

        $researchCompletedPayload = ['status' => ResearchStatus::RESEARCH_COMPLETED];
        if ($locked->first_completed_at === null) {
            $researchCompletedPayload['first_completed_at'] = now();
        }

        $locked->update($researchCompletedPayload);

        $this->writeAuditLog($actor, $locked, 'research.completion.research_completed', [
            'status' => $sourceStatus,
            'final_review_count' => $countBefore,
        ], [
            'status' => ResearchStatus::RESEARCH_COMPLETED,
            'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
            'final_review_count' => $countBefore,
        ]);

        ResearchStatus::assertTransition(ResearchStatus::RESEARCH_COMPLETED, ResearchStatus::FINAL_DEAN_REVIEW);

        $finalAttributes = ['status' => ResearchStatus::FINAL_DEAN_REVIEW];
        if ($incrementFinalReviewCount) {
            $finalAttributes['final_review_count'] = $iteration;
        }

        $locked->update($finalAttributes);

        Approval::query()->create([
            'research_id' => $locked->id,
            'approver_id' => $actor->id,
            'stage' => 'faculty',
            'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
            'final_review_iteration' => $iteration > 0 ? $iteration : null,
            'action' => 'completion_submitted',
            'remarks' => $this->normalizeOptionalRemarks($remarks),
            'acted_at' => now(),
        ]);

        $this->writeAuditLog($actor, $locked, $completionAuditAction, [
            'status' => ResearchStatus::RESEARCH_COMPLETED,
            'final_review_count' => $countBefore,
        ], [
            'status' => ResearchStatus::FINAL_DEAN_REVIEW,
            'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
            'final_review_count' => $locked->final_review_count,
        ]);
    }

    /**
     * @deprecated Use resubmitInitial() or resubmitFinal().
     */
    public function resubmit(Research $research, User $actor): void
    {
        if ($research->status === ResearchStatus::INITIAL_REJECTED) {
            $this->resubmitInitial($research, $actor);

            return;
        }

        if ($research->status === ResearchStatus::FINAL_REJECTED) {
            throw ValidationException::withMessages([
                'status' => [__('Use the outcome update form to revise classifications and resubmit for final review.')],
            ]);
        }

        throw ValidationException::withMessages([
            'status' => [__('This research cannot be resubmitted in its current stage.')],
        ]);
    }

    private function sendBack(
        Research $research,
        User $actor,
        string $remarks,
        string $stage,
        string $action,
    ): void {
        $this->assertRemarksNonEmpty($remarks);

        if (! in_array($stage, ['dean', 'ovpri'], true)) {
            throw ValidationException::withMessages([
                'stage' => [__('Invalid return stage.')],
            ]);
        }

        if (! in_array($action, ['returned', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'action' => [__('Invalid review action.')],
            ]);
        }

        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor, $remarks, $stage, $action) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);
            $cycle = ResearchStatus::reviewCycle($locked->status);

            if ($stage === 'dean') {
                if ($locked->status === ResearchStatus::INITIAL_DEAN_REVIEW) {
                    $this->assertDeanMayActOnResearch($actor, $locked);
                    $this->assertDeanReviewActionPermission($actor, $action);

                    $this->createApproval($locked, $actor, 'dean', ResearchStatus::REVIEW_CYCLE_INITIAL, $action, $remarks);

                    $oldStatus = $locked->status;
                    $locked->update([
                        'status' => ResearchStatus::INITIAL_REJECTED,
                        'revision_count' => $locked->revision_count + 1,
                    ]);

                    $this->writeAuditLog($actor, $locked, 'approval.initial.'.$action, [
                        'status' => $oldStatus,
                        'revision_count' => $locked->revision_count - 1,
                    ], [
                        'status' => ResearchStatus::INITIAL_REJECTED,
                        'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
                        'revision_count' => $locked->revision_count,
                    ]);

                    return;
                }

                if ($locked->status === ResearchStatus::FINAL_DEAN_REVIEW) {
                    $this->assertDeanMayActOnResearch($actor, $locked);
                    $this->assertDeanReviewActionPermission($actor, $action);

                    $this->createApproval(
                        $locked,
                        $actor,
                        'dean',
                        ResearchStatus::REVIEW_CYCLE_FINAL,
                        $action,
                        $remarks,
                        (int) $locked->final_review_count,
                    );

                    $oldStatus = $locked->status;
                    $locked->update(['status' => ResearchStatus::FINAL_REJECTED]);

                    $this->writeAuditLog($actor, $locked, 'approval.final.'.$action, [
                        'status' => $oldStatus,
                    ], [
                        'status' => ResearchStatus::FINAL_REJECTED,
                        'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
                        'final_review_count' => $locked->final_review_count,
                    ]);

                    return;
                }
            }

            if ($stage === 'ovpri') {
                if (! $actor->can('approval.return') && $action === 'returned') {
                    throw ValidationException::withMessages([
                        'user' => [__('You are not allowed to return research at this stage.')],
                    ]);
                }

                if ($locked->status === ResearchStatus::INITIAL_OVPRI_REVIEW) {
                    $this->createApproval($locked, $actor, 'ovpri', ResearchStatus::REVIEW_CYCLE_INITIAL, $action, $remarks);

                    $oldStatus = $locked->status;
                    $locked->update([
                        'status' => ResearchStatus::INITIAL_REJECTED,
                        'revision_count' => $locked->revision_count + 1,
                    ]);

                    $this->writeAuditLog($actor, $locked, 'approval.initial.'.$action, [
                        'status' => $oldStatus,
                        'revision_count' => $locked->revision_count - 1,
                    ], [
                        'status' => ResearchStatus::INITIAL_REJECTED,
                        'review_cycle' => ResearchStatus::REVIEW_CYCLE_INITIAL,
                        'revision_count' => $locked->revision_count,
                    ]);

                    return;
                }

                if ($locked->status === ResearchStatus::FINAL_OVPRI_REVIEW) {
                    $this->createApproval(
                        $locked,
                        $actor,
                        'ovpri',
                        ResearchStatus::REVIEW_CYCLE_FINAL,
                        $action,
                        $remarks,
                        (int) $locked->final_review_count,
                    );

                    $oldStatus = $locked->status;
                    $locked->update(['status' => ResearchStatus::FINAL_REJECTED]);

                    $this->writeAuditLog($actor, $locked, 'approval.final.'.$action, [
                        'status' => $oldStatus,
                    ], [
                        'status' => ResearchStatus::FINAL_REJECTED,
                        'review_cycle' => ResearchStatus::REVIEW_CYCLE_FINAL,
                        'final_review_count' => $locked->final_review_count,
                    ]);

                    return;
                }
            }

            throw ValidationException::withMessages([
                'status' => [__('Research cannot be returned or rejected in its current stage.')],
            ]);
        });

        $fresh = Research::query()->with('primaryAuthor')->findOrFail($researchId);

        if (in_array($fresh->status, [ResearchStatus::INITIAL_REJECTED, ResearchStatus::FINAL_REJECTED], true)) {
            $returnedBy = $stage === 'ovpri' ? 'ovpri' : 'dean';
            $fresh->primaryAuthor?->notify(new ResearchReturned($fresh, $remarks, $returnedBy));

            if ($stage === 'ovpri') {
                foreach (ResearchDeanRouting::deanUsersFor($fresh) as $dean) {
                    $dean->notify(new ResearchReturnedToDean($fresh, $remarks));
                }
            }
        }
    }

    private function createApproval(
        Research $research,
        User $approver,
        string $stage,
        string $reviewCycle,
        string $action,
        ?string $remarks = null,
        ?int $finalReviewIteration = null,
    ): void {
        Approval::query()->create([
            'research_id' => $research->id,
            'approver_id' => $approver->id,
            'stage' => $stage,
            'review_cycle' => $reviewCycle,
            'final_review_iteration' => $finalReviewIteration,
            'action' => $action,
            'remarks' => $this->normalizeOptionalRemarks($remarks),
            'acted_at' => now(),
        ]);
    }

    private function assertDeanReviewActionPermission(User $dean, string $action): void
    {
        if ($action === 'returned' && ! $dean->can('approval.return')) {
            throw ValidationException::withMessages([
                'user' => [__('You are not allowed to return research at this stage.')],
            ]);
        }
    }

    private function assertDeanPermission(User $dean): void
    {
        if (! $dean->can('approval.endorse')) {
            throw ValidationException::withMessages([
                'user' => [__('You are not allowed to endorse research.')],
            ]);
        }
    }

    private function assertDeanMayActOnResearch(User $dean, Research $research): void
    {
        if (ResearchDeanRouting::deanMayActOnResearch($dean, $research)) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => [__('You may only act on research for your college.')],
        ]);
    }

    private function assertRemarksNonEmpty(string $remarks): void
    {
        if (trim($remarks) === '') {
            throw ValidationException::withMessages([
                'remarks' => [__('Remarks are required.')],
            ]);
        }
    }

    private function normalizeOptionalRemarks(?string $remarks): ?string
    {
        if ($remarks === null) {
            return null;
        }

        $trimmed = trim($remarks);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<int>|null
     */
    private function normalizeOtherCollegeIds(mixed $value, int $motherCollegeId): ?array
    {
        if ($value === null || $value === []) {
            return null;
        }

        $ids = array_values(array_unique(array_map('intval', is_array($value) ? $value : [$value])));
        $ids = array_values(array_filter(
            $ids,
            fn (int $id) => $id > 0 && $id !== $motherCollegeId,
        ));

        return $ids === [] ? null : $ids;
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function writeAuditLog(User $actor, Research $research, string $action, array $oldValues, array $newValues): void
    {
        AuditLog::query()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Research::class,
            'auditable_id' => $research->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip() ?? '0.0.0.0',
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function allocateReferenceNumber(int $collegeId): string
    {
        $college = College::query()->findOrFail($collegeId);
        $year = (string) now()->year;
        $prefix = 'AUF-'.$year.'-'.$college->code.'-';

        return DB::transaction(function () use ($prefix) {
            $last = Research::withTrashed()
                ->where('reference_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference_number')
                ->value('reference_number');

            $next = 1;
            if ($last !== null && preg_match('/(\d{4})$/', $last, $m)) {
                $next = (int) $m[1] + 1;
            }

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    private function notifyDeansOfResubmit(int $researchId): void
    {
        $research = Research::query()
            ->with(['primaryAuthor', 'researchAuthors', 'motherCollege'])
            ->find($researchId);

        if ($research === null) {
            return;
        }

        foreach (ResearchDeanRouting::deanUsersFor($research) as $dean) {
            $dean->notify(new ResearchResubmitted($research));
        }
    }
}

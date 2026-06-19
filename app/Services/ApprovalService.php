<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Notifications\ResearchEndorsed;
use App\Notifications\ResearchEndorsedToOvpri;
use App\Notifications\ResearchReturned;
use App\Notifications\ResearchReturnedToDean;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
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

    public function paginateResearchForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->latest();

        if ($user->hasRole('registrar')) {
            $query->where('approval_stage', 'approved');
        } elseif ($user->can('research.view_all')) {
            // university-wide (OVPRI/CDAIC/super_admin when permitted)
        } elseif ($user->can('research.view_college')) {
            $query->where('mother_college_id', $user->college_id);
        } elseif ($user->can('research.view_own')) {
            $query->where(function ($q) use ($user) {
                $q->where('primary_author_id', $user->id)
                    ->orWhereHas('researchAuthors', fn ($qq) => $qq->where('user_id', $user->id));
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->paginate($perPage);
    }

    public function paginateAllResearch(
        int $perPage = 20,
        ?string $college = null,
        ?string $stage = null,
    ): LengthAwarePaginator {
        return Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->when($college, fn ($q) => $q->where('mother_college_id', $college))
            ->when($stage, fn ($q) => $q->where('approval_stage', $stage))
            ->latest()
            ->paginate($perPage);
    }

    public function createDraftAfterRegistrationType(User $user, string $registrationType): Research
    {
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
                'title' => __('Untitled research'),
                'primary_author_id' => $user->id,
                'mother_college_id' => $collegeId,
                'research_classification' => 'other',
                'funding_agency' => null,
                'sdg_tags' => [],
                'expected_output' => ['publication'],
                'expected_output_other' => null,
                'start_date' => now()->toDateString(),
                'estimated_completion_date' => now()->addYear()->toDateString(),
                'status' => 'proposal',
                'approval_stage' => 'draft',
                'revision_count' => 0,
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
                ResearchAuthor::query()->create([
                    'research_id' => $research->id,
                    'user_id' => null,
                    'employee_number' => $row['employee_number'] ?? null,
                    'name' => $row['name'],
                    'college_id' => ! empty($row['college_id']) ? (int) $row['college_id'] : null,
                    'is_primary' => false,
                    'can_edit' => false,
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createResearch(User $user, array $data): Research
    {
        return DB::transaction(function () use ($user, $data) {
            $referenceNumber = $this->allocateReferenceNumber((int) $data['mother_college_id']);

            return Research::query()->create([
                'reference_number' => $referenceNumber,
                'registration_type' => $data['registration_type'],
                'title' => $data['title'],
                'primary_author_id' => $user->id,
                'mother_college_id' => $data['mother_college_id'],
                'other_college_id' => $data['other_college_id'] ?? null,
                'research_classification' => $data['research_classification'],
                'funding_agency' => $data['funding_agency'] ?? null,
                'sdg_tags' => $data['sdg_tags'] ?? [],
                'expected_output' => $data['expected_output'],
                'expected_output_other' => $data['expected_output_other'] ?? null,
                'start_date' => $data['start_date'],
                'estimated_completion_date' => $data['estimated_completion_date'],
                'status' => $data['status'] ?? 'proposal',
                'approval_stage' => 'draft',
                'revision_count' => 0,
                'is_scopus_indexed' => false,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateResearch(Research $research, array $data): void
    {
        $research->update([
            'registration_type' => $data['registration_type'],
            'title' => $data['title'],
            'mother_college_id' => $data['mother_college_id'],
            'other_college_id' => $data['other_college_id'] ?? null,
            'research_classification' => $data['research_classification'],
            'funding_agency' => $data['funding_agency'] ?? null,
            'sdg_tags' => $data['sdg_tags'] ?? [],
            'expected_output' => $data['expected_output'],
            'expected_output_other' => $data['expected_output_other'] ?? null,
            'start_date' => $data['start_date'],
            'estimated_completion_date' => $data['estimated_completion_date'],
            'status' => $data['status'] ?? $research->status,
        ]);
    }

    /**
     * [draft] ──submit()──► [dean_review] (KMSAR §8).
     */
    public function submit(Research $research, User $actor): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->approval_stage !== 'draft') {
                throw ValidationException::withMessages([
                    'approval_stage' => [__('Research must be in draft to submit for review.')],
                ]);
            }

            if (! $locked->documents()->exists()) {
                throw ValidationException::withMessages([
                    'documents' => [__('At least one document is required before submission.')],
                ]);
            }

            $oldStage = $locked->approval_stage;
            $locked->update(['approval_stage' => 'dean_review']);

            $this->writeAuditLog($actor, $locked, 'research.submitted', [
                'approval_stage' => $oldStage,
            ], [
                'approval_stage' => 'dean_review',
            ]);
        });

        SendNotificationJob::dispatch($researchId, 'submitted');
    }

    /**
     * [dean_review] ──endorse()──► [ovpri_review] (KMSAR §8).
     */
    public function endorse(Research $research, User $dean, ?string $remarks = null): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $dean, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->approval_stage !== 'dean_review') {
                throw ValidationException::withMessages([
                    'approval_stage' => [__('Research must be awaiting dean review to endorse.')],
                ]);
            }

            $this->assertDeanMayActOnResearch($dean, $locked);

            if (! $dean->can('approval.endorse')) {
                throw ValidationException::withMessages([
                    'user' => [__('You are not allowed to endorse research.')],
                ]);
            }

            Approval::query()->create([
                'research_id' => $locked->id,
                'approver_id' => $dean->id,
                'stage' => 'dean',
                'action' => 'endorsed',
                'remarks' => $this->normalizeOptionalRemarks($remarks),
                'acted_at' => now(),
            ]);

            $oldStage = $locked->approval_stage;
            $locked->update(['approval_stage' => 'ovpri_review']);

            $this->writeAuditLog($dean, $locked, 'approval.endorsed', [
                'approval_stage' => $oldStage,
            ], [
                'approval_stage' => 'ovpri_review',
            ]);
        });

        $fresh = Research::query()->with('primaryAuthor')->findOrFail($researchId);
        $fresh->primaryAuthor?->notify(new ResearchEndorsed($fresh));

        User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['ovpri_admin', 'cdaic_admin']))
            ->get()
            ->each(fn (User $admin) => $admin->notify(new ResearchEndorsedToOvpri($fresh)));
    }

    /**
     * [ovpri_review] ──approve()──► [approved] (KMSAR §8).
     */
    public function approve(Research $research, User $ovpri, ?string $remarks = null): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $ovpri, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->approval_stage !== 'ovpri_review') {
                throw ValidationException::withMessages([
                    'approval_stage' => [__('Research must be awaiting OVPRI review to approve.')],
                ]);
            }

            if (! $ovpri->can('approval.approve')) {
                throw ValidationException::withMessages([
                    'user' => [__('You are not allowed to approve research at this stage.')],
                ]);
            }

            Approval::query()->create([
                'research_id' => $locked->id,
                'approver_id' => $ovpri->id,
                'stage' => 'ovpri',
                'action' => 'approved',
                'remarks' => $this->normalizeOptionalRemarks($remarks),
                'acted_at' => now(),
            ]);

            $oldStage = $locked->approval_stage;
            $locked->update(['approval_stage' => 'approved']);

            $this->writeAuditLog($ovpri, $locked, 'approval.approved', [
                'approval_stage' => $oldStage,
            ], [
                'approval_stage' => 'approved',
            ]);
        });

        SendNotificationJob::dispatch($researchId, 'approved');
    }

    /**
     * [dean_review] ──return()──► [draft] · [ovpri_review] ──return()──► [dean_review] (KMSAR §8).
     */
    public function return(Research $research, User $actor, string $remarks): void
    {
        $this->assertRemarksNonEmpty($remarks);

        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->approval_stage === 'dean_review') {
                $this->assertDeanMayActOnResearch($actor, $locked);
                if (! $actor->can('approval.return')) {
                    throw ValidationException::withMessages([
                        'user' => [__('You are not allowed to return research at this stage.')],
                    ]);
                }

                Approval::query()->create([
                    'research_id' => $locked->id,
                    'approver_id' => $actor->id,
                    'stage' => 'dean',
                    'action' => 'returned',
                    'remarks' => $remarks,
                    'acted_at' => now(),
                ]);

                $oldStage = $locked->approval_stage;
                $locked->update([
                    'approval_stage' => 'draft',
                    'revision_count' => $locked->revision_count + 1,
                ]);

                $this->writeAuditLog($actor, $locked, 'approval.returned', [
                    'approval_stage' => $oldStage,
                    'revision_count' => $locked->revision_count - 1,
                ], [
                    'approval_stage' => 'draft',
                    'revision_count' => $locked->revision_count,
                ]);

                return;
            }

            if ($locked->approval_stage === 'ovpri_review') {
                if (! $actor->can('approval.return')) {
                    throw ValidationException::withMessages([
                        'user' => [__('You are not allowed to return research at this stage.')],
                    ]);
                }

                Approval::query()->create([
                    'research_id' => $locked->id,
                    'approver_id' => $actor->id,
                    'stage' => 'ovpri',
                    'action' => 'returned',
                    'remarks' => $remarks,
                    'acted_at' => now(),
                ]);

                $oldStage = $locked->approval_stage;
                $locked->update([
                    'approval_stage' => 'dean_review',
                    'revision_count' => $locked->revision_count + 1,
                ]);

                $this->writeAuditLog($actor, $locked, 'approval.returned', [
                    'approval_stage' => $oldStage,
                    'revision_count' => $locked->revision_count - 1,
                ], [
                    'approval_stage' => 'dean_review',
                    'revision_count' => $locked->revision_count,
                ]);

                return;
            }

            throw ValidationException::withMessages([
                'approval_stage' => [__('Research cannot be returned in its current approval stage.')],
            ]);
        });

        $fresh = Research::query()->with('primaryAuthor')->findOrFail($researchId);

        if ($fresh->approval_stage === 'draft') {
            $fresh->primaryAuthor?->notify(new ResearchReturned($fresh));
        } elseif ($fresh->approval_stage === 'dean_review') {
            $collegeDean = User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'college_dean'))
                ->where('college_id', $fresh->mother_college_id)
                ->first();

            $collegeDean?->notify(new ResearchReturnedToDean($fresh));
        }
    }

    /**
     * [dean_review] ──reject()──► [rejected] · [ovpri_review] ──reject()──► [rejected] (KMSAR §8).
     */
    public function reject(Research $research, User $actor, string $remarks): void
    {
        $this->assertRemarksNonEmpty($remarks);

        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor, $remarks) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->approval_stage === 'dean_review') {
                $this->assertDeanMayActOnResearch($actor, $locked);
                if (! $actor->can('approval.reject')) {
                    throw ValidationException::withMessages([
                        'user' => [__('You are not allowed to reject research at this stage.')],
                    ]);
                }

                Approval::query()->create([
                    'research_id' => $locked->id,
                    'approver_id' => $actor->id,
                    'stage' => 'dean',
                    'action' => 'rejected',
                    'remarks' => $remarks,
                    'acted_at' => now(),
                ]);

                $oldStage = $locked->approval_stage;
                $locked->update(['approval_stage' => 'rejected']);

                $this->writeAuditLog($actor, $locked, 'approval.rejected', [
                    'approval_stage' => $oldStage,
                ], [
                    'approval_stage' => 'rejected',
                ]);

                return;
            }

            if ($locked->approval_stage === 'ovpri_review') {
                if (! $actor->can('approval.reject')) {
                    throw ValidationException::withMessages([
                        'user' => [__('You are not allowed to reject research at this stage.')],
                    ]);
                }

                Approval::query()->create([
                    'research_id' => $locked->id,
                    'approver_id' => $actor->id,
                    'stage' => 'ovpri',
                    'action' => 'rejected',
                    'remarks' => $remarks,
                    'acted_at' => now(),
                ]);

                $oldStage = $locked->approval_stage;
                $locked->update(['approval_stage' => 'rejected']);

                $this->writeAuditLog($actor, $locked, 'approval.rejected', [
                    'approval_stage' => $oldStage,
                ], [
                    'approval_stage' => 'rejected',
                ]);

                return;
            }

            throw ValidationException::withMessages([
                'approval_stage' => [__('Research cannot be rejected in its current approval stage.')],
            ]);
        });

        SendNotificationJob::dispatch($researchId, 'rejected');
    }

    /**
     * [rejected] ──resubmit()──► [draft] (KMSAR §8).
     */
    public function resubmit(Research $research, User $actor): void
    {
        $researchId = (int) $research->getKey();

        DB::transaction(function () use ($researchId, $actor) {
            $locked = Research::query()->lockForUpdate()->findOrFail($researchId);

            if ($locked->approval_stage !== 'rejected') {
                throw ValidationException::withMessages([
                    'approval_stage' => [__('Only rejected research can be moved back to draft for resubmission.')],
                ]);
            }

            $oldStage = $locked->approval_stage;
            $locked->update(['approval_stage' => 'draft']);

            $this->writeAuditLog($actor, $locked, 'research.resubmitted', [
                'approval_stage' => $oldStage,
            ], [
                'approval_stage' => 'draft',
            ]);
        });

        SendNotificationJob::dispatch($researchId, 'resubmitted');
    }

    private function assertDeanMayActOnResearch(User $dean, Research $research): void
    {
        if ((int) $dean->college_id !== (int) $research->mother_college_id) {
            throw ValidationException::withMessages([
                'user' => [__('You may only act on research for your college.')],
            ]);
        }
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
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApprovalActionRequest;
use App\Mail\ResearchApprovedDeanMail;
use App\Mail\ResearchApprovedFacultyMail;
use App\Mail\ResearchEndorsedFacultyMail;
use App\Mail\ResearchEndorsedOvpriMail;
use App\Mail\ResearchReturnedByOvpriDeanMail;
use App\Mail\ResearchReturnedByOvpriFacultyMail;
use App\Mail\ResearchReturnedFacultyMail;
use App\Models\College;
use App\Models\Research;
use App\Models\User;
use App\Notifications\ResearchApproved;
use App\Notifications\ResearchApprovedDean;
use App\Services\ApprovalService;
use App\Services\DashboardCacheService;
use App\Support\ResearchDeanRouting;
use App\Support\ResearchStatus;
use App\Support\SafeMail;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ApprovalService $approvalService
    ) {}

    public function queue(Request $request): View
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin');
        $cycleTab = $request->query('cycle', 'initial');
        if (! in_array($cycleTab, ['initial', 'final'], true)) {
            $cycleTab = 'initial';
        }

        $ovpriStatuses = $cycleTab === 'final'
            ? [ResearchStatus::FINAL_OVPRI_REVIEW, ResearchStatus::RESEARCH_ACCEPTED]
            : [ResearchStatus::INITIAL_OVPRI_REVIEW, ResearchStatus::RESEARCH_REGISTERED];

        $rejectedStatus = $cycleTab === 'final'
            ? ResearchStatus::FINAL_REJECTED
            : ResearchStatus::INITIAL_REJECTED;

        // Dean queue is scoped by mother_college_id (routing college), not the author's home college.
        $pending = Research::query()
            ->with(['motherCollege', 'primaryAuthor', 'outcomeClassifications'])
            ->when(! $isSuperAdmin, fn ($q) => $q->forDeanCollege($request->user()))
            ->whereIn('status', [ResearchStatus::INITIAL_DEAN_REVIEW, ResearchStatus::FINAL_DEAN_REVIEW])
            ->orderBy('submitted_at', 'asc')
            ->get();

        $endorsed = Research::query()
            ->with(['motherCollege', 'primaryAuthor', 'outcomeClassifications'])
            ->when(! $isSuperAdmin, fn ($q) => $q->forDeanCollege($request->user()))
            ->whereIn('status', $ovpriStatuses)
            ->whereHas('approvals', function ($q) use ($request, $isSuperAdmin, $cycleTab) {
                $q->where('stage', 'dean')
                    ->where('action', 'endorsed')
                    ->where('review_cycle', $cycleTab);
                if (! $isSuperAdmin) {
                    $q->where('approver_id', $request->user()->id);
                }
            })
            ->orderByDesc('updated_at')
            ->get();

        $returned = Research::query()
            ->with(['motherCollege', 'primaryAuthor', 'outcomeClassifications'])
            ->when(! $isSuperAdmin, fn ($q) => $q->forDeanCollege($request->user()))
            ->where('status', $rejectedStatus)
            ->whereHas('approvals', function ($q) use ($request, $isSuperAdmin, $cycleTab) {
                $q->where('review_cycle', $cycleTab)
                    ->where('action', 'returned');
                if (! $isSuperAdmin) {
                    $q->where('approver_id', $request->user()->id);
                }
            })
            ->orderByDesc('updated_at')
            ->get();

        return view('approval.queue', compact('pending', 'endorsed', 'returned', 'cycleTab'));
    }

    public function review(Request $request, Research $research): View
    {
        $this->authorize('view', $research);
        $this->authorizeCollegeScope($request, $research);

        $isActiveDeanQueue = ResearchStatus::isDeanQueueStatus((string) $research->status);
        $hasDeanHistory = $research->approvals()
            ->where('approver_id', $request->user()->id)
            ->where('stage', 'dean')
            ->exists();
        abort_unless(
            $isActiveDeanQueue || $hasDeanHistory || $request->user()->hasRole('super_admin'),
            403
        );

        $research->load([
            'motherCollege',
            'primaryAuthor.college',
            'primaryAuthor.program',
            'researchAuthors.college',
            'researchAuthors.program',
            'documents',
            'outcomeClassifications',
            'approvals' => fn ($q) => $q->orderBy('created_at'),
            'approvals.approver',
        ]);

        return view('approval.review', [
            'research' => $research,
        ]);
    }

    public function endorse(ApprovalActionRequest $request, Research $research): RedirectResponse
    {
        $this->authorize('view', $research);
        abort_unless(ResearchStatus::isDeanQueueStatus((string) $research->status), 403);
        $this->authorizeCollegeScope($request, $research);

        $validated = $request->validated();

        $this->approvalService->endorse($research, $request->user(), $validated['remarks'] ?? null);

        $research->refresh()->loadMissing(['primaryAuthor', 'researchAuthors', 'motherCollege']);

        $delay = 0;

        if ($research->primaryAuthor?->email) {
            SafeMail::send($research->primaryAuthor->email, new ResearchEndorsedFacultyMail($research), $delay);
            $delay += 2;
        }

        $research->researchAuthors
            ->where('is_primary', false)
            ->filter(fn ($author) => filled($author->email))
            ->each(function ($author) use ($research, &$delay) {
                SafeMail::send($author->email, new ResearchEndorsedFacultyMail($research), $delay);
                $delay += 2;
            });

        User::whereHas('roles', fn ($q) => $q->whereIn('name', ['ovpri_admin', 'cdaic_admin']))
            ->each(function (User $ovpri) use ($research, &$delay) {
                SafeMail::send($ovpri->email, new ResearchEndorsedOvpriMail($research, $ovpri), $delay);
                $delay += 2;
            });

        $this->forgetResearchDashboardCaches($research);

        $cycle = ResearchStatus::reviewCycle($research->status) ?? 'initial';

        return redirect()
            ->route('approval.queue', ['cycle' => $cycle === ResearchStatus::REVIEW_CYCLE_FINAL ? 'final' : 'initial'])
            ->with('success', __('Research has been endorsed and forwarded to OVPRI.'));
    }

    public function returnSubmission(ApprovalActionRequest $request, Research $research): RedirectResponse
    {
        $this->authorize('view', $research);
        abort_unless(ResearchStatus::isDeanQueueStatus((string) $research->status), 403);
        $this->authorizeCollegeScope($request, $research);

        $validated = $request->validated();

        $this->approvalService->return($research, $request->user(), $validated['remarks'], 'dean');

        $research->refresh()->loadMissing(['primaryAuthor', 'researchAuthors']);

        if ($research->primaryAuthor?->email) {
            SafeMail::send(
                $research->primaryAuthor->email,
                new ResearchReturnedFacultyMail($research, $validated['remarks']),
                0
            );
        }

        $research->researchAuthors
            ->where('is_primary', false)
            ->filter(fn ($author) => filled($author->email))
            ->each(function ($author) use ($research, $validated) {
                SafeMail::send(
                    $author->email,
                    new ResearchReturnedFacultyMail($research, $validated['remarks']),
                    0
                );
            });

        $this->forgetResearchDashboardCaches($research);

        $cycle = ResearchStatus::reviewCycle($research->status) ?? ResearchStatus::REVIEW_CYCLE_INITIAL;

        return redirect()
            ->route('approval.queue', ['cycle' => $cycle === ResearchStatus::REVIEW_CYCLE_FINAL ? 'final' : 'initial'])
            ->with('success', __('Research has been returned to the author for revision.'));
    }

    public function ovpriQueue(Request $request): View
    {
        $selectedCollege = $request->filled('college_id') ? (int) $request->integer('college_id') : null;
        $activeTab = in_array($request->query('tab'), ['pending', 'approved', 'returned'], true)
            ? $request->query('tab')
            : 'pending';
        $cycleTab = $request->query('cycle', 'initial');
        if (! in_array($cycleTab, ['initial', 'final'], true)) {
            $cycleTab = 'initial';
        }

        $colleges = College::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $baseQuery = function () use ($selectedCollege) {
            $query = Research::query()->with(['motherCollege', 'primaryAuthor', 'outcomeClassifications']);

            if ($selectedCollege !== null) {
                $query->where('mother_college_id', $selectedCollege);
            }

            return $query;
        };

        $approvedStatuses = $cycleTab === 'final'
            ? [ResearchStatus::RESEARCH_ACCEPTED]
            : [ResearchStatus::RESEARCH_REGISTERED];

        // Pending: load both cycles — the queue view splits Initial/Final client-side.
        $pending = $baseQuery()
            ->whereIn('status', [ResearchStatus::INITIAL_OVPRI_REVIEW, ResearchStatus::FINAL_OVPRI_REVIEW])
            ->orderByDesc('submitted_at')
            ->get();

        $approved = $baseQuery()
            ->whereIn('status', $approvedStatuses)
            ->whereHas('approvals', function ($q) use ($cycleTab) {
                $q->where('stage', 'ovpri')
                    ->where('action', 'approved')
                    ->where('review_cycle', $cycleTab);
            })
            ->orderByDesc('updated_at')
            ->get();

        $returned = $baseQuery()
            ->where('status', $cycleTab === 'final' ? ResearchStatus::FINAL_REJECTED : ResearchStatus::INITIAL_REJECTED)
            ->whereHas('approvals', function ($q) use ($cycleTab) {
                $q->where('stage', 'ovpri')
                    ->where('review_cycle', $cycleTab)
                    ->where('action', 'returned');
            })
            ->orderByDesc('updated_at')
            ->get();

        return view('ovpri.queue', compact(
            'pending',
            'approved',
            'returned',
            'colleges',
            'selectedCollege',
            'activeTab',
            'cycleTab'
        ));
    }

    public function approve(ApprovalActionRequest $request, Research $research): RedirectResponse
    {
        $this->authorizeOvpriStageAction($request, $research);

        $validated = $request->validated();

        $this->approvalService->approve($research, $request->user(), $validated['remarks'] ?? null);

        $research->refresh();

        $research->primaryAuthor?->notify(
            new ResearchApproved($research)
        );

        $dean = User::whereHas('roles', function ($q) {
            $q->where('name', 'college_dean');
        })
            ->where('college_id', $research->mother_college_id)
            ->first();

        $delay = 0;

        if ($dean) {
            $dean->notify(new ResearchApprovedDean($research));
            SafeMail::send($dean->email, new ResearchApprovedDeanMail($research, $dean), $delay);
            $delay += 2;
        }

        if ($research->primaryAuthor?->email) {
            SafeMail::send($research->primaryAuthor->email, new ResearchApprovedFacultyMail($research), $delay);
            $delay += 2;
        }

        $research->loadMissing('researchAuthors');
        $research->researchAuthors
            ->where('is_primary', false)
            ->filter(fn ($author) => filled($author->email))
            ->each(function ($author) use ($research, &$delay) {
                SafeMail::send($author->email, new ResearchApprovedFacultyMail($research), $delay);
                $delay += 2;
            });

        $this->forgetResearchDashboardCaches($research);

        $cycle = $research->status === ResearchStatus::RESEARCH_ACCEPTED ? 'final' : 'initial';

        return redirect()
            ->route('ovpri.queue', ['cycle' => $cycle, 'tab' => 'approved'])
            ->with('success', __('Research has been approved successfully.'));
    }

    public function ovpriReturn(ApprovalActionRequest $request, Research $research): RedirectResponse
    {
        $this->authorizeOvpriStageAction($request, $research);

        $validated = $request->validated();

        $this->approvalService->return($research, $request->user(), $validated['remarks'], 'ovpri');

        $research->refresh()->loadMissing(['primaryAuthor', 'researchAuthors']);

        $delay = 0;

        if ($research->primaryAuthor?->email) {
            SafeMail::send(
                $research->primaryAuthor->email,
                new ResearchReturnedByOvpriFacultyMail($research, $validated['remarks']),
                $delay
            );
            $delay += 2;
        }

        $dean = User::whereHas('roles', function ($q) {
            $q->where('name', 'college_dean');
        })
            ->where('college_id', $research->mother_college_id)
            ->first();

        if ($dean) {
            SafeMail::send(
                $dean->email,
                new ResearchReturnedByOvpriDeanMail($research, $dean, $validated['remarks']),
                $delay
            );
            $delay += 2;
        }

        $research->researchAuthors
            ->where('is_primary', false)
            ->filter(fn ($author) => filled($author->email))
            ->each(function ($author) use ($research, $validated, &$delay) {
                SafeMail::send(
                    $author->email,
                    new ResearchReturnedByOvpriFacultyMail($research, $validated['remarks']),
                    $delay
                );
                $delay += 2;
            });

        $this->forgetResearchDashboardCaches($research);

        $cycle = ResearchStatus::reviewCycle($research->status) ?? ResearchStatus::REVIEW_CYCLE_INITIAL;

        return redirect()
            ->route('ovpri.queue', ['cycle' => $cycle === ResearchStatus::REVIEW_CYCLE_FINAL ? 'final' : 'initial', 'tab' => 'returned'])
            ->with('success', __('Research has been returned to the faculty for revision.'));
    }

    private function forgetResearchDashboardCaches(Research $research): void
    {
        unset($research);

        DashboardCacheService::flush();
    }

    private function authorizeCollegeScope(Request $request, Research $research): void
    {
        $user = $request->user();

        if ($user->hasRole('super_admin')) {
            return;
        }

        abort_unless(
            ResearchDeanRouting::deanMayActOnResearch($user, $research),
            403,
            __('You may only act on research for your college.')
        );
    }

    private function authorizeOvpriStageAction(Request $request, Research $research): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']),
            403,
            __('You are not authorized to perform this action.')
        );

        abort_unless(ResearchStatus::isOvpriQueueStatus((string) $research->status), 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Research;
use App\Models\User;
use App\Notifications\ResearchApproved;
use App\Notifications\ResearchApprovedDean;
use App\Notifications\ResearchRejected;
use App\Notifications\ResearchRejectedDean;
use App\Services\ApprovalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ApprovalService $approvalService
    ) {}

    public function queue(Request $request): View
    {
        $collegeId = $request->user()->college_id;

        $pending = Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->where('mother_college_id', $collegeId)
            ->where('approval_stage', 'dean_review')
            ->orderBy('submitted_at', 'asc')
            ->get();

        $endorsed = Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->where('mother_college_id', $collegeId)
            ->whereIn('approval_stage', ['ovpri_review', 'approved'])
            ->whereHas('approvals', function ($q) use ($request) {
                $q->where('approver_id', $request->user()->id)
                    ->where('stage', 'dean')
                    ->where('action', 'endorsed');
            })
            ->orderByDesc('updated_at')
            ->get();

        $returned = Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->where('mother_college_id', $collegeId)
            ->whereNotIn('approval_stage', ['dean_review', 'ovpri_review', 'approved'])
            ->whereHas('approvals', function ($q) use ($request) {
                $q->where('approver_id', $request->user()->id)
                    ->where('stage', 'dean')
                    ->whereIn('action', ['returned', 'rejected']);
            })
            ->orderByDesc('updated_at')
            ->get();

        return view('approval.queue', compact('pending', 'endorsed', 'returned'));
    }

    public function review(Request $request, Research $research): View
    {
        $this->authorize('view', $research);
        abort_unless((int) $research->mother_college_id === (int) $request->user()->college_id, 403);

        $isActiveDeanQueue = $research->approval_stage === 'dean_review';
        $hasDeanHistory = $research->approvals()
            ->where('approver_id', $request->user()->id)
            ->where('stage', 'dean')
            ->exists();
        abort_unless($isActiveDeanQueue || $hasDeanHistory, 403);

        $research->load([
            'motherCollege',
            'primaryAuthor.college',
            'primaryAuthor.program',
            'researchAuthors.college',
            'researchAuthors.program',
            'documents',
            'approvals' => fn ($q) => $q->orderBy('created_at'),
            'approvals.approver',
        ]);

        return view('approval.review', [
            'research' => $research,
        ]);
    }

    public function endorse(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('view', $research);
        abort_unless($research->approval_stage === 'dean_review', 403);
        abort_unless((int) $research->mother_college_id === (int) $request->user()->college_id, 403);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $this->approvalService->endorse($research, $request->user(), $validated['remarks']);

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('approval.queue')
            ->with('success', __('Research has been endorsed and forwarded to OVPRI.'));
    }

    /**
     * Dean / unit head return-to-faculty (architecture: ApprovalController::return — PHP reserves "return").
     */
    public function returnSubmission(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('view', $research);
        abort_unless($research->approval_stage === 'dean_review', 403);
        abort_unless((int) $research->mother_college_id === (int) $request->user()->college_id, 403);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $this->approvalService->return($research, $request->user(), $validated['remarks']);

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('approval.queue')
            ->with('success', __('Research has been returned to the author for revision.'));
    }

    public function reject(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('view', $research);
        abort_unless($research->approval_stage === 'dean_review', 403);
        abort_unless((int) $research->mother_college_id === (int) $request->user()->college_id, 403);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $this->approvalService->reject($research, $request->user(), $validated['remarks']);

        $research->refresh();

        $dean = User::whereHas('roles', function ($q) {
            $q->where('name', 'college_dean');
        })
            ->where('college_id', $research->mother_college_id)
            ->first();

        if ($dean) {
            $dean->notify(new ResearchRejectedDean($research));
        }

        $research->primaryAuthor?->notify(
            new ResearchRejected($research, $validated['remarks'], 'dean')
        );

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('approval.queue')
            ->with('success', __('Research submission has been rejected.'));
    }

    public function ovpriQueue(Request $request): View
    {
        $selectedCollege = $request->filled('college_id') ? (int) $request->integer('college_id') : null;
        $activeTab = in_array($request->query('tab'), ['pending', 'approved', 'returned'], true)
            ? $request->query('tab')
            : 'pending';

        $colleges = College::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $baseQuery = function () use ($selectedCollege) {
            $query = Research::query()->with(['motherCollege', 'primaryAuthor']);

            if ($selectedCollege !== null) {
                $query->where('mother_college_id', $selectedCollege);
            }

            return $query;
        };

        $pending = $baseQuery()
            ->where('approval_stage', 'ovpri_review')
            ->orderByDesc('submitted_at')
            ->get();

        $approved = $baseQuery()
            ->where('approval_stage', 'approved')
            ->whereHas('approvals', function ($q) {
                $q->where('stage', 'ovpri')
                    ->where('action', 'approved');
            })
            ->orderByDesc('updated_at')
            ->get();

        $returned = $baseQuery()
            ->whereNotIn('approval_stage', ['ovpri_review', 'approved', 'dean_review'])
            ->whereHas('approvals', function ($q) {
                $q->where('stage', 'ovpri')
                    ->whereIn('action', ['returned', 'rejected']);
            })
            ->orderByDesc('updated_at')
            ->get();

        return view('ovpri.queue', compact(
            'pending',
            'approved',
            'returned',
            'colleges',
            'selectedCollege',
            'activeTab'
        ));
    }

    public function approve(Request $request, Research $research): RedirectResponse
    {
        $this->authorizeOvpriStageAction($request, $research);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

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

        if ($dean) {
            $dean->notify(new ResearchApprovedDean($research));
        }

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('ovpri.queue')
            ->with('success', __('Research has been approved successfully.'));
    }

    public function ovpriReturn(Request $request, Research $research): RedirectResponse
    {
        $this->authorizeOvpriStageAction($request, $research);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $this->approvalService->return($research, $request->user(), $validated['remarks']);

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('ovpri.queue')
            ->with('success', __('Research has been returned to the college for dean review.'));
    }

    public function ovpriReject(Request $request, Research $research): RedirectResponse
    {
        $this->authorizeOvpriStageAction($request, $research);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $this->approvalService->reject($research, $request->user(), $validated['remarks']);

        $research->refresh();

        $dean = User::whereHas('roles', function ($q) {
            $q->where('name', 'college_dean');
        })
            ->where('college_id', $research->mother_college_id)
            ->first();

        if ($dean) {
            $dean->notify(new ResearchRejectedDean($research));
        }

        $research->primaryAuthor?->notify(
            new ResearchRejected($research, $validated['remarks'], 'ovpri')
        );

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('ovpri.queue')
            ->with('success', __('Research submission has been rejected.'));
    }

    private function forgetResearchDashboardCaches(Research $research): void
    {
        $hourKey = now()->format('Y-m-d-H');
        Cache::forget('ovpri_stats_all_'.$hourKey);
        for ($year = now()->year - 9; $year <= now()->year + 1; $year++) {
            Cache::forget('ovpri_stats_'.$year.'_'.$hourKey);
        }
        Cache::forget('admin_monthly_stats_'.now()->format('Y-m'));
        Cache::forget('sdg_counts');
        Cache::forget('sdg_counts_all');
        for ($year = now()->year - 9; $year <= now()->year + 1; $year++) {
            Cache::forget('sdg_counts_'.$year);
        }

        foreach ($this->deanUserIdsForCollege((int) $research->mother_college_id) as $id) {
            Cache::forget('dean_stats_'.$id.'_all_'.now()->format('Y-m-d'));
            for ($year = now()->year - 9; $year <= now()->year + 1; $year++) {
                Cache::forget('dean_stats_'.$id.'_'.$year.'_'.now()->format('Y-m-d'));
            }
        }
    }

    /**
     * OVPRI and CDAIC admins may approve, return, or reject any research in ovpri_review.
     */
    private function authorizeOvpriStageAction(Request $request, Research $research): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasAnyRole(['ovpri_admin', 'cdaic_admin']),
            403,
            __('You are not authorized to perform this action.')
        );

        abort_unless($research->approval_stage === 'ovpri_review', 403);
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
}

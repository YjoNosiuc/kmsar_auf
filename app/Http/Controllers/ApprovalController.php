<?php

namespace App\Http\Controllers;

use App\Mail\ResearchApprovedDeanMail;
use App\Mail\ResearchApprovedFacultyMail;
use App\Mail\ResearchEndorsedFacultyMail;
use App\Mail\ResearchEndorsedOvpriMail;
use App\Mail\ResearchRejectedDeanMail;
use App\Mail\ResearchRejectedFacultyMail;
use App\Mail\ResearchReturnedByOvpriDeanMail;
use App\Mail\ResearchReturnedByOvpriFacultyMail;
use App\Mail\ResearchReturnedFacultyMail;
use App\Models\College;
use App\Models\Research;
use App\Models\User;
use App\Notifications\ResearchApproved;
use App\Notifications\ResearchApprovedDean;
use App\Notifications\ResearchRejected;
use App\Notifications\ResearchRejectedDean;
use App\Services\ApprovalService;
use App\Support\SafeMail;
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
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin');
        $collegeId = $user->college_id;

        $pending = Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->when(! $isSuperAdmin, fn ($q) => $q->where('mother_college_id', $collegeId))
            ->where('approval_stage', 'dean_review')
            ->orderBy('submitted_at', 'asc')
            ->get();

        $endorsed = Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->when(! $isSuperAdmin, fn ($q) => $q->where('mother_college_id', $collegeId))
            ->whereIn('approval_stage', ['ovpri_review', 'approved'])
            ->whereHas('approvals', function ($q) use ($request, $isSuperAdmin) {
                $q->where('stage', 'dean')
                    ->where('action', 'endorsed');
                if (! $isSuperAdmin) {
                    $q->where('approver_id', $request->user()->id);
                }
            })
            ->orderByDesc('updated_at')
            ->get();

        $returned = Research::query()
            ->with(['motherCollege', 'primaryAuthor'])
            ->when(! $isSuperAdmin, fn ($q) => $q->where('mother_college_id', $collegeId))
            ->where(function ($q) use ($request, $isSuperAdmin) {
                // Dean returns/rejects OR OVPRI returns awaiting faculty revision (info only).
                $q->whereHas('approvals', function ($inner) use ($request, $isSuperAdmin) {
                    $inner->where('stage', 'dean')
                        ->whereIn('action', ['returned', 'rejected']);
                    if (! $isSuperAdmin) {
                        $inner->where('approver_id', $request->user()->id);
                    }
                })->orWhere('approval_stage', 'returned_to_faculty');
            })
            ->whereNotIn('approval_stage', ['dean_review', 'ovpri_review', 'approved'])
            ->orderByDesc('updated_at')
            ->get();

        return view('approval.queue', compact('pending', 'endorsed', 'returned'));
    }

    public function review(Request $request, Research $research): View
    {
        $this->authorize('view', $research);
        $this->authorizeCollegeScope($request, $research);

        $isActiveDeanQueue = $research->approval_stage === 'dean_review';
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
        $this->authorizeCollegeScope($request, $research);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

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
        $this->authorizeCollegeScope($request, $research);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:4', 'max:5000'],
        ]);

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

        return redirect()
            ->route('approval.queue')
            ->with('success', __('Research has been returned to the author for revision.'));
    }

    public function reject(Request $request, Research $research): RedirectResponse
    {
        $this->authorize('view', $research);
        abort_unless($research->approval_stage === 'dean_review', 403);
        $this->authorizeCollegeScope($request, $research);

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

        $delay = 0;

        if ($research->primaryAuthor?->email) {
            SafeMail::send(
                $research->primaryAuthor->email,
                new ResearchRejectedFacultyMail($research, $validated['remarks']),
                $delay
            );
            $delay += 2;
        }

        $research->loadMissing('researchAuthors');
        $research->researchAuthors
            ->where('is_primary', false)
            ->filter(fn ($author) => filled($author->email))
            ->each(function ($author) use ($research, $validated, &$delay) {
                SafeMail::send(
                    $author->email,
                    new ResearchRejectedFacultyMail($research, $validated['remarks']),
                    $delay
                );
                $delay += 2;
            });

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
            ->whereNotIn('approval_stage', ['ovpri_review', 'approved'])
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

        return redirect()
            ->route('ovpri.queue')
            ->with('success', __('Research has been approved successfully.'));
    }

    public function ovpriReturn(Request $request, Research $research): RedirectResponse
    {
        $this->authorizeOvpriStageAction($request, $research);

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'min:4', 'max:5000'],
        ]);

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

        return redirect()
            ->route('ovpri.queue')
            ->with('success', __('Research has been returned to the faculty for revision.'));
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

        $delay = 0;

        if ($dean) {
            $dean->notify(new ResearchRejectedDean($research));
            SafeMail::send(
                $dean->email,
                new ResearchRejectedDeanMail($research, $dean, $validated['remarks']),
                $delay
            );
            $delay += 2;
        }

        $research->primaryAuthor?->notify(
            new ResearchRejected($research, $validated['remarks'], 'ovpri')
        );

        if ($research->primaryAuthor?->email) {
            SafeMail::send(
                $research->primaryAuthor->email,
                new ResearchRejectedFacultyMail($research, $validated['remarks']),
                $delay
            );
            $delay += 2;
        }

        $research->loadMissing('researchAuthors');
        $research->researchAuthors
            ->where('is_primary', false)
            ->filter(fn ($author) => filled($author->email))
            ->each(function ($author) use ($research, $validated, &$delay) {
                SafeMail::send(
                    $author->email,
                    new ResearchRejectedFacultyMail($research, $validated['remarks']),
                    $delay
                );
                $delay += 2;
            });

        $this->forgetResearchDashboardCaches($research);

        return redirect()
            ->route('ovpri.queue')
            ->with('success', __('Research submission has been rejected.'));
    }

    private function forgetResearchDashboardCaches(Research $research): void
    {
        foreach ([now(), now()->subHour()] as $moment) {
            $hourKey = $moment->format('Y-m-d-H');
            Cache::forget('ovpri_dash_v4_all_all_'.$hourKey);
            Cache::forget('ovpri_dash_v3_all_all_'.$hourKey);
            Cache::forget('ovpri_dash_v2_all_all_'.$hourKey);
            Cache::forget('ovpri_stats_all_all_'.$hourKey);
            Cache::forget('ovpri_stats_all_'.$hourKey);
        }
        $monthKey = now()->format('Y-m');
        Cache::forget('admin_monthly_stats_v2_all_all_'.$monthKey);
        Cache::forget('admin_monthly_stats_all_all_'.$monthKey);
        Cache::forget('admin_monthly_stats_'.$monthKey);
        Cache::forget('sdg_counts_v2_all_all');
        Cache::forget('sdg_counts');
        Cache::forget('sdg_counts_all');
        Cache::forget('sdg_counts_all_all');

        foreach ($this->deanUserIdsForCollege((int) $research->mother_college_id) as $id) {
            foreach ([now(), now()->subDay()] as $day) {
                $dayKey = $day->format('Y-m-d');
                Cache::forget('dean_stats_v2_'.$id.'_all_all_'.$dayKey);
                Cache::forget('dean_stats_'.$id.'_all_all_'.$dayKey);
                Cache::forget('dean_stats_'.$id.'_all_'.$dayKey);
            }
        }
    }

    /**
     * Dean / unit head act only on their own college. Super admins are university-wide
     * and carry no college_id, so they are not college-scoped.
     */
    private function authorizeCollegeScope(Request $request, Research $research): void
    {
        $user = $request->user();

        if ($user->hasRole('super_admin')) {
            return;
        }

        abort_unless(
            (int) $research->mother_college_id === (int) $user->college_id,
            403,
            __('You may only act on research for your college.')
        );
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

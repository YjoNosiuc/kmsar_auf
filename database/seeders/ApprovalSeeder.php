<?php

namespace Database\Seeders;

use App\Models\Research;
use App\Models\User;
use App\Support\ResearchStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $deanCcs = User::query()->where('email', 'dean.ccs@yopmail.com')->firstOrFail();
        $deanCamp = User::query()->where('email', 'dean.camp@yopmail.com')->firstOrFail();
        $ovpri = User::query()->where('email', 'ovpri@yopmail.com')->firstOrFail();

        DB::table('approvals')->truncate();

        $insert = function (
            string $reference,
            User $approver,
            string $stage,
            string $action,
            string $actedAt,
            ?string $remarks = null,
            ?string $reviewCycle = null,
            ?int $finalReviewIteration = null,
        ): void {
            $research = Research::query()->where('reference_number', $reference)->firstOrFail();
            $ts = Carbon::parse($actedAt);
            $cycle = $reviewCycle ?? ResearchStatus::reviewCycle((string) $research->status);

            if ($cycle === ResearchStatus::REVIEW_CYCLE_INITIAL) {
                $cycle = ResearchStatus::REVIEW_CYCLE_INITIAL;
            } elseif ($cycle === ResearchStatus::REVIEW_CYCLE_FINAL) {
                $cycle = ResearchStatus::REVIEW_CYCLE_FINAL;
            } elseif (in_array($research->status, ResearchStatus::initialReviewStatuses(), true)) {
                $cycle = ResearchStatus::REVIEW_CYCLE_INITIAL;
            } elseif (in_array($research->status, ResearchStatus::finalReviewStatuses(), true)) {
                $cycle = ResearchStatus::REVIEW_CYCLE_FINAL;
            } elseif ($research->status === ResearchStatus::RESEARCH_ACCEPTED) {
                $cycle = ResearchStatus::REVIEW_CYCLE_FINAL;
            } elseif ($research->status === ResearchStatus::ONGOING) {
                $cycle = ResearchStatus::REVIEW_CYCLE_INITIAL;
            }

            DB::table('approvals')->insert([
                'research_id' => $research->id,
                'approver_id' => $approver->id,
                'stage' => $stage,
                'review_cycle' => $cycle,
                'final_review_iteration' => $finalReviewIteration ?? ($cycle === ResearchStatus::REVIEW_CYCLE_FINAL ? max(1, (int) $research->final_review_count) : null),
                'action' => $action,
                'remarks' => $remarks,
                'acted_at' => $ts,
                'created_at' => $ts,
            ]);
        };

        // Drafts: CCS-0001, CAMP-0001 — no approvals yet
        // Initial dean review: CCS-0002, CCS-0004 — submitted, awaiting dean

        // Ongoing (initial cycle completed): CCS-0007
        $insert('AUF-2025-CCS-0007', $deanCcs, 'dean', 'endorsed', '2025-01-12 10:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CCS-0007', $ovpri, 'ovpri', 'approved', '2025-01-20 14:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);

        // Final OVPRI review: CCS-0005
        $insert('AUF-2025-CCS-0005', $deanCcs, 'dean', 'endorsed', '2025-06-05 10:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CCS-0005', $ovpri, 'ovpri', 'approved', '2025-06-15 11:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CCS-0005', $deanCcs, 'dean', 'endorsed', '2025-07-01 10:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);

        // Initial OVPRI review: CAMP-0002
        $insert('AUF-2025-CAMP-0002', $deanCamp, 'dean', 'endorsed', '2025-06-02 09:30:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);

        // Final dean review: CAMP-0004
        $insert('AUF-2025-CAMP-0004', $deanCamp, 'dean', 'endorsed', '2025-07-22 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CAMP-0004', $ovpri, 'ovpri', 'approved', '2025-07-28 11:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);

        // Research accepted records — full dual-cycle approval history
        $insert('AUF-2025-CCS-0003', $deanCcs, 'dean', 'endorsed', '2025-03-20 10:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CCS-0003', $ovpri, 'ovpri', 'approved', '2025-03-28 14:30:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CCS-0003', $deanCcs, 'dean', 'endorsed', '2025-04-05 10:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);
        $insert('AUF-2025-CCS-0003', $ovpri, 'ovpri', 'approved', '2025-04-12 14:30:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);

        $insert('AUF-2025-CCS-0006', $deanCcs, 'dean', 'endorsed', '2025-05-05 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CCS-0006', $ovpri, 'ovpri', 'approved', '2025-05-15 11:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CCS-0006', $deanCcs, 'dean', 'endorsed', '2025-05-20 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);
        $insert('AUF-2025-CCS-0006', $ovpri, 'ovpri', 'approved', '2025-05-28 11:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);

        $insert('AUF-2025-CAMP-0003', $deanCamp, 'dean', 'endorsed', '2025-02-10 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CAMP-0003', $ovpri, 'ovpri', 'approved', '2025-02-20 14:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CAMP-0003', $deanCamp, 'dean', 'endorsed', '2025-02-25 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);
        $insert('AUF-2025-CAMP-0003', $ovpri, 'ovpri', 'approved', '2025-03-01 14:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);

        $insert('AUF-2025-CAMP-0005', $deanCamp, 'dean', 'endorsed', '2025-04-10 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CAMP-0005', $ovpri, 'ovpri', 'approved', '2025-04-22 11:15:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CAMP-0005', $deanCamp, 'dean', 'endorsed', '2025-04-28 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);
        $insert('AUF-2025-CAMP-0005', $ovpri, 'ovpri', 'approved', '2025-05-05 11:15:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);

        $insert('AUF-2025-CAMP-0006', $deanCamp, 'dean', 'endorsed', '2025-03-01 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CAMP-0006', $ovpri, 'ovpri', 'approved', '2025-03-12 14:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_INITIAL);
        $insert('AUF-2025-CAMP-0006', $deanCamp, 'dean', 'endorsed', '2025-03-18 09:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);
        $insert('AUF-2025-CAMP-0006', $ovpri, 'ovpri', 'approved', '2025-03-25 14:00:00', reviewCycle: ResearchStatus::REVIEW_CYCLE_FINAL);
    }
}

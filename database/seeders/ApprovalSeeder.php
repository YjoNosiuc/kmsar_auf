<?php

namespace Database\Seeders;

use App\Models\Research;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApprovalSeeder extends Seeder
{
    public function run(): void
    {
        // Approvers
        $deanCcs  = User::where('email', 'dean.ccs@auf.edu.ph')->firstOrFail();
        $deanCba  = User::where('email', 'dean.cba@auf.edu.ph')->firstOrFail();
        $deanCea  = User::where('email', 'dean.cea@auf.edu.ph')->firstOrFail();
        $ovpri    = User::where('email', 'ovpri@auf.edu.ph')->firstOrFail();

        // Clear all existing approvals
        DB::table('approvals')->truncate();

        $insert = function (
            Research $research,
            User $approver,
            string $stage,
            string $action,
            string $actedAt,
            ?string $remarks = null
        ): void {
            $ts = Carbon::parse($actedAt);
            DB::table('approvals')->insert([
                'research_id' => $research->id,
                'approver_id' => $approver->id,
                'stage'       => $stage,
                'action'      => $action,
                'remarks'     => $remarks,
                'acted_at'    => $ts,
                'created_at'  => $ts,
            ]);
        };

        // ── CCS ──────────────────────────────────────────────────

        // CCS-0001 — returned by dean (still in dean_review)
        $r = Research::where('reference_number', 'AUF-2024-CCS-0001')->firstOrFail();
        $insert($r, $deanCcs, 'dean', 'returned', '2024-03-10 09:00:00',
            'Please add quantitative results and statistical analysis before endorsement.');

        // CCS-0002 — fully approved
        $r = Research::where('reference_number', 'AUF-2024-CCS-0002')->firstOrFail();
        $insert($r, $deanCcs,  'dean',  'endorsed', '2024-03-20 10:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-03-28 14:30:00');

        // CCS-0003 — fully approved (scopus)
        $r = Research::where('reference_number', 'AUF-2024-CCS-0003')->firstOrFail();
        $insert($r, $deanCcs,  'dean',  'endorsed', '2024-05-05 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-05-15 11:00:00');

        // CCS-0004 — draft, no approvals yet

        // CCS-0005 — endorsed by dean, awaiting OVPRI
        $r = Research::where('reference_number', 'AUF-2024-CCS-0005')->firstOrFail();
        $insert($r, $deanCcs, 'dean', 'endorsed', '2024-07-01 10:00:00');

        // CCS-0006 — fully approved
        $r = Research::where('reference_number', 'AUF-2024-CCS-0006')->firstOrFail();
        $insert($r, $deanCcs,  'dean',  'endorsed', '2024-08-10 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-08-20 14:00:00');

        // CCS-0007 — pending dean review, no approvals yet

        // CCS-2023-0001 — fully approved (2023)
        $r = Research::where('reference_number', 'AUF-2023-CCS-0001')->firstOrFail();
        $insert($r, $deanCcs,  'dean',  'endorsed', '2023-04-01 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2023-04-12 11:00:00');

        // CCS-2023-0002 — fully approved (2023)
        $r = Research::where('reference_number', 'AUF-2023-CCS-0002')->firstOrFail();
        $insert($r, $deanCcs,  'dean',  'endorsed', '2023-06-15 10:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2023-06-25 14:00:00');

        // ── CBA ──────────────────────────────────────────────────

        // CBA-0001 — fully approved
        $r = Research::where('reference_number', 'AUF-2024-CBA-0001')->firstOrFail();
        $insert($r, $deanCba,  'dean',  'endorsed', '2024-02-10 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-02-20 14:00:00');

        // CBA-0002 — pending dean review, no approvals yet

        // CBA-0003 — fully approved
        $r = Research::where('reference_number', 'AUF-2024-CBA-0003')->firstOrFail();
        $insert($r, $deanCba,  'dean',  'endorsed', '2024-04-01 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-04-15 14:00:00');

        // CBA-0004 — endorsed by dean, awaiting OVPRI
        $r = Research::where('reference_number', 'AUF-2024-CBA-0004')->firstOrFail();
        $insert($r, $deanCba, 'dean', 'endorsed', '2024-05-01 09:00:00');

        // CBA-0005 — draft, no approvals yet

        // CBA-2023-0001 — fully approved (2023)
        $r = Research::where('reference_number', 'AUF-2023-CBA-0001')->firstOrFail();
        $insert($r, $deanCba,  'dean',  'endorsed', '2023-07-10 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2023-07-20 14:00:00');

        // ── CEA ──────────────────────────────────────────────────

        // CEA-0001 — fully approved
        $r = Research::where('reference_number', 'AUF-2024-CEA-0001')->firstOrFail();
        $insert($r, $deanCea,  'dean',  'endorsed', '2024-03-01 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-03-12 14:00:00');

        // CEA-0002 — fully approved (scopus)
        $r = Research::where('reference_number', 'AUF-2024-CEA-0002')->firstOrFail();
        $insert($r, $deanCea,  'dean',  'endorsed', '2024-04-10 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-04-22 14:00:00');

        // CEA-0003 — returned by dean first, then re-submitted pending review
        $r = Research::where('reference_number', 'AUF-2024-CEA-0003')->firstOrFail();
        $insert($r, $deanCea, 'dean', 'returned', '2024-06-01 09:00:00',
            'Please include traffic simulation data and comparison with existing systems.');

        // CEA-0004 — endorsed by dean, awaiting OVPRI
        $r = Research::where('reference_number', 'AUF-2024-CEA-0004')->firstOrFail();
        $insert($r, $deanCea, 'dean', 'endorsed', '2024-07-15 09:00:00');

        // CEA-0005 — fully approved
        $r = Research::where('reference_number', 'AUF-2024-CEA-0005')->firstOrFail();
        $insert($r, $deanCea,  'dean',  'endorsed', '2024-09-01 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2024-09-12 14:00:00');

        // CEA-0006 — draft, no approvals yet

        // CEA-2023-0001 — fully approved (2023)
        $r = Research::where('reference_number', 'AUF-2023-CEA-0001')->firstOrFail();
        $insert($r, $deanCea,  'dean',  'endorsed', '2023-05-15 09:00:00');
        $insert($r, $ovpri,    'ovpri', 'approved', '2023-05-25 14:00:00');
    }
}

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
            ?string $remarks = null
        ): void {
            $research = Research::query()->where('reference_number', $reference)->firstOrFail();
            $ts = Carbon::parse($actedAt);

            DB::table('approvals')->insert([
                'research_id' => $research->id,
                'approver_id' => $approver->id,
                'stage' => $stage,
                'action' => $action,
                'remarks' => $remarks,
                'acted_at' => $ts,
                'created_at' => $ts,
            ]);
        };

        // Drafts: CCS-0001, CAMP-0001 — no approvals yet
        // Dean review: CCS-0002, CCS-0004, CAMP-0004 — submitted, awaiting dean

        $insert('AUF-2025-CCS-0005', $deanCcs, 'dean', 'endorsed', '2025-07-01 10:00:00');
        $insert('AUF-2025-CAMP-0002', $deanCamp, 'dean', 'endorsed', '2025-06-02 09:30:00');

        $insert('AUF-2025-CCS-0003', $deanCcs, 'dean', 'endorsed', '2025-03-20 10:00:00');
        $insert('AUF-2025-CCS-0003', $ovpri, 'ovpri', 'approved', '2025-03-28 14:30:00');

        $insert('AUF-2025-CCS-0006', $deanCcs, 'dean', 'endorsed', '2025-05-05 09:00:00');
        $insert('AUF-2025-CCS-0006', $ovpri, 'ovpri', 'approved', '2025-05-15 11:00:00');

        $insert('AUF-2025-CAMP-0003', $deanCamp, 'dean', 'endorsed', '2025-02-10 09:00:00');
        $insert('AUF-2025-CAMP-0003', $ovpri, 'ovpri', 'approved', '2025-02-20 14:00:00');

        $insert('AUF-2025-CAMP-0005', $deanCamp, 'dean', 'endorsed', '2025-04-10 09:00:00');
        $insert('AUF-2025-CAMP-0005', $ovpri, 'ovpri', 'approved', '2025-04-22 11:15:00');

        $insert('AUF-2025-CAMP-0006', $deanCamp, 'dean', 'endorsed', '2025-03-01 09:00:00');
        $insert('AUF-2025-CAMP-0006', $ovpri, 'ovpri', 'approved', '2025-03-12 14:00:00');
    }
}

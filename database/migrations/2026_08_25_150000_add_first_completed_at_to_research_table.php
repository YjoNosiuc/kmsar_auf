<?php

use App\Models\AuditLog;
use App\Models\Research;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research', function (Blueprint $table) {
            $table->timestamp('first_completed_at')->nullable()->after('research_accepted_at');
            $table->index('first_completed_at');
        });

        $this->backfillFirstCompletedAtFromAuditLogs();
    }

    public function down(): void
    {
        Schema::table('research', function (Blueprint $table) {
            $table->dropIndex(['first_completed_at']);
            $table->dropColumn('first_completed_at');
        });
    }

    /**
     * Backfill first_completed_at for records that already have outcome classifications.
     *
     * Uses the earliest audit_logs row with action research.completion.research_completed.
     * Rows with outcomes but no matching audit log are left null — see console note after migrate.
     */
    private function backfillFirstCompletedAtFromAuditLogs(): void
    {
        if (! Schema::hasTable('research_outcome')) {
            return;
        }

        $researchIds = DB::table('research')
            ->whereNull('first_completed_at')
            ->whereNull('deleted_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('research_outcome')
                    ->whereColumn('research_outcome.research_id', 'research.id');
            })
            ->pluck('id');

        $backfilled = 0;

        foreach ($researchIds as $researchId) {
            $auditAt = AuditLog::query()
                ->where('auditable_type', Research::class)
                ->where('auditable_id', $researchId)
                ->where('action', 'research.completion.research_completed')
                ->orderBy('created_at')
                ->value('created_at');

            if ($auditAt === null) {
                continue;
            }

            DB::table('research')
                ->where('id', $researchId)
                ->update(['first_completed_at' => $auditAt]);

            $backfilled++;
        }

        $remaining = DB::table('research')
            ->whereNull('first_completed_at')
            ->whereNull('deleted_at')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('research_outcome')
                    ->whereColumn('research_outcome.research_id', 'research.id');
            })
            ->count();

        if ($remaining > 0 && app()->runningInConsole()) {
            echo "\n[KMSAR] {$remaining} research record(s) with outcome classifications have no first_completed_at "
                ."(no research.completion.research_completed audit log found). Backfilled {$backfilled} row(s).\n";
        }
    }
};

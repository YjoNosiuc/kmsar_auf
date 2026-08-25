<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outcome_classifications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 200);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('research_outcome', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')->constrained('research')->cascadeOnDelete();
            $table->foreignId('outcome_classification_id')->constrained('outcome_classifications')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['research_id', 'outcome_classification_id'], 'research_outcome_uq');
        });

        if (Schema::hasColumn('research', 'registration_type')) {
            DB::table('research')->where('registration_type', 'update')->update(['registration_type' => 'existing']);
        }

        Schema::table('research', function (Blueprint $table) {
            $table->unsignedInteger('final_review_count')->default(0)->after('revision_count');
            $table->timestamp('research_registered_at')->nullable()->after('submitted_at');
            $table->timestamp('research_accepted_at')->nullable()->after('research_registered_at');
        });

        $this->migrateLegacyStatusColumns();

        Schema::table('research', function (Blueprint $table) {
            if (Schema::hasColumn('research', 'approval_stage')) {
                $table->dropIndex(['approval_stage']);
                $table->dropColumn('approval_stage');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE research MODIFY COLUMN registration_type ENUM('new', 'existing') NOT NULL DEFAULT 'new'");
            DB::statement("ALTER TABLE research MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'proposal'");
        }

        Schema::table('approvals', function (Blueprint $table) {
            $table->enum('review_cycle', ['initial', 'final'])->nullable()->after('stage');
            $table->unsignedInteger('final_review_iteration')->nullable()->after('review_cycle');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approvals MODIFY COLUMN action ENUM('endorsed', 'approved', 'returned', 'rejected', 'progress_update', 'completion_submitted') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE approvals MODIFY COLUMN action ENUM('endorsed', 'approved', 'returned', 'rejected', 'progress_update') NOT NULL");
        }

        Schema::table('approvals', function (Blueprint $table) {
            $table->dropColumn(['review_cycle', 'final_review_iteration']);
        });

        Schema::table('research', function (Blueprint $table) {
            $table->enum('approval_stage', ['draft', 'dean_review', 'ovpri_review', 'approved', 'rejected', 'returned_to_faculty'])
                ->default('draft')
                ->after('status');
            $table->dropColumn(['final_review_count', 'research_registered_at', 'research_accepted_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE research MODIFY COLUMN registration_type ENUM('new', 'update') NOT NULL DEFAULT 'new'");
        }

        Schema::dropIfExists('research_outcome');
        Schema::dropIfExists('outcome_classifications');
    }

    private function migrateLegacyStatusColumns(): void
    {
        if (! Schema::hasColumn('research', 'approval_stage')) {
            return;
        }

        $completedStatuses = [
            'completed_unpublished',
            'presented_internal',
            'presented_external',
            'published_non_indexed',
            'published_scopus',
            'patent_submitted',
            'patent_granted',
        ];

        $rows = DB::table('research')->select('id', 'status', 'approval_stage')->get();

        foreach ($rows as $row) {
            $newStatus = match ($row->approval_stage) {
                'draft' => 'proposal',
                'dean_review' => in_array($row->status, $completedStatuses, true) ? 'final_dean_review' : 'initial_dean_review',
                'ovpri_review' => in_array($row->status, $completedStatuses, true) ? 'final_ovpri_review' : 'initial_ovpri_review',
                'approved' => in_array($row->status, $completedStatuses, true) ? 'research_accepted' : 'ongoing',
                'rejected', 'returned_to_faculty' => in_array($row->status, $completedStatuses, true) ? 'final_rejected' : 'initial_rejected',
                default => 'proposal',
            };

            $updates = ['status' => $newStatus];

            if (in_array($newStatus, ['ongoing', 'research_accepted'], true)) {
                $updates['research_registered_at'] = DB::raw('COALESCE(submitted_at, created_at)');
            }

            if ($newStatus === 'research_accepted') {
                $updates['research_accepted_at'] = DB::raw('updated_at');
            }

            DB::table('research')->where('id', $row->id)->update($updates);
        }
    }
};

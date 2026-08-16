<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('research')) {
            return;
        }

        // approval_stage is a MySQL ENUM — extend with returned_to_faculty.
        DB::statement("ALTER TABLE research MODIFY COLUMN approval_stage ENUM('draft', 'dean_review', 'ovpri_review', 'approved', 'rejected', 'returned_to_faculty') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('research')) {
            return;
        }

        // Move any returned_to_faculty rows back before shrinking the enum.
        DB::table('research')
            ->where('approval_stage', 'returned_to_faculty')
            ->update(['approval_stage' => 'draft']);

        DB::statement("ALTER TABLE research MODIFY COLUMN approval_stage ENUM('draft', 'dean_review', 'ovpri_review', 'approved', 'rejected') NOT NULL DEFAULT 'draft'");
    }
};

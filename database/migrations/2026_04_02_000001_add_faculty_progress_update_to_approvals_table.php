<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE approvals MODIFY COLUMN stage ENUM('dean', 'ovpri', 'faculty') NOT NULL");
        DB::statement("ALTER TABLE approvals MODIFY COLUMN action ENUM('endorsed', 'approved', 'returned', 'rejected', 'progress_update') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE approvals MODIFY COLUMN stage ENUM('dean', 'ovpri') NOT NULL");
        DB::statement("ALTER TABLE approvals MODIFY COLUMN action ENUM('endorsed', 'approved', 'returned', 'rejected') NOT NULL");
    }
};

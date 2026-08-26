<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('research')
            ->where('status', 'proposal')
            ->update(['status' => 'draft']);
    }

    public function down(): void
    {
        DB::table('research')
            ->where('status', 'draft')
            ->whereNull('submitted_at')
            ->update(['status' => 'proposal']);
    }
};

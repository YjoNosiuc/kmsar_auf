<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('research')
            ->where('status', 'ongoing')
            ->update(['status' => 'research_registered']);
    }

    public function down(): void
    {
        DB::table('research')
            ->where('status', 'research_registered')
            ->update(['status' => 'ongoing']);
    }
};

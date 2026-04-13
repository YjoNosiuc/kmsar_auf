<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('research_authors', function (Blueprint $table) {
            $table->string('college_text', 255)->nullable()->after('college_id');
            $table->string('program', 255)->nullable()->after('college_text');
            $table->string('email', 255)->nullable()->after('program');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_authors', function (Blueprint $table) {
            $table->dropColumn(['college_text', 'program', 'email']);
        });
    }
};

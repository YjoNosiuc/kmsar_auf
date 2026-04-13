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
            $table->string('author_type', 32)->nullable()->after('user_id');
            $table->foreignId('program_id')->nullable()->after('college_id')->constrained('programs')->nullOnDelete();
            $table->foreignId('affiliated_college_id')->nullable()->after('program_id')->constrained('colleges')->nullOnDelete();
            $table->string('institution', 255)->nullable()->after('affiliated_college_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_authors', function (Blueprint $table) {
            $table->dropForeign(['program_id']);
            $table->dropForeign(['affiliated_college_id']);
            $table->dropColumn(['author_type', 'program_id', 'affiliated_college_id', 'institution']);
        });
    }
};

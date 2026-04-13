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
        Schema::table('research', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('is_scopus_indexed');
            $table->index('registration_type');
            $table->index('research_classification');
            $table->index(['status', 'approval_stage']);
            $table->index(['mother_college_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_scopus_indexed']);
            $table->dropIndex(['registration_type']);
            $table->dropIndex(['research_classification']);
            $table->dropIndex(['status', 'approval_stage']);
            $table->dropIndex(['mother_college_id', 'status']);
        });
    }
};

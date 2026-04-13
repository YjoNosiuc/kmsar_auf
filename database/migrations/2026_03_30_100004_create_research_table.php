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
        Schema::create('research', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();
            $table->enum('registration_type', ['new', 'update']);
            $table->text('title');
            $table->foreignId('primary_author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('mother_college_id')->constrained('colleges')->restrictOnDelete();
            $table->string('research_classification', 60);
            $table->string('funding_agency', 100)->nullable();
            $table->json('sdg_tags');
            $table->enum('expected_output', ['publication', 'patent', 'policy_brief', 'other']);
            $table->date('start_date');
            $table->date('estimated_completion_date');
            $table->string('status', 40);
            $table->enum('approval_stage', ['draft', 'dean_review', 'ovpri_review', 'approved', 'rejected']);
            $table->unsignedInteger('revision_count')->default(0);
            $table->boolean('is_scopus_indexed')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index('mother_college_id');
            $table->index('approval_stage');
            $table->index('status');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('research', function (Blueprint $table) {
                $table->fullText('title');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research');
    }
};

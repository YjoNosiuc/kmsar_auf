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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')->constrained('research')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->enum('stage', ['dean', 'ovpri', 'faculty']);
            $table->enum('action', ['endorsed', 'approved', 'returned', 'rejected', 'progress_update']);
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['research_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};

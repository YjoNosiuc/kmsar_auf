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
        Schema::create('research_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_id')->constrained('research')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_number', 20)->nullable();
            $table->string('name', 150);
            $table->foreignId('college_id')->nullable()->constrained('colleges')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_edit')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('research_authors');
    }
};

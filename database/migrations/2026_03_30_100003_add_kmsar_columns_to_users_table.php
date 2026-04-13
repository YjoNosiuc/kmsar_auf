<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_number', 20)->nullable()->unique()->after('id');
            $table->foreignId('college_id')->nullable()->after('password')->constrained('colleges')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('college_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
        }

        Schema::table('colleges', function (Blueprint $table) {
            $table->foreign('head_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colleges', function (Blueprint $table) {
            $table->dropForeign(['head_user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['college_id']);
            $table->dropColumn(['employee_number', 'college_id', 'is_active', 'last_login_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (! Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('last_name');
            }
            if (! Schema::hasColumn('users', 'suffix')) {
                $table->string('suffix')->nullable()->after('middle_name');
            }
            if (! Schema::hasColumn('users', 'employee_number')) {
                $table->string('employee_number')->nullable()->unique()->after('suffix');
            }
            if (! Schema::hasColumn('users', 'college_id')) {
                $table->foreignId('college_id')->nullable()->constrained('colleges')->nullOnDelete()->after('employee_number');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('college_id');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'suffix')) {
                $table->dropColumn('suffix');
            }
            if (Schema::hasColumn('users', 'middle_name')) {
                $table->dropColumn('middle_name');
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });
    }
};

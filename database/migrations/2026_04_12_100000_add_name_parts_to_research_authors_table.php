<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_authors', function (Blueprint $table) {
            $table->string('first_name', 150)->nullable()->after('employee_number');
            $table->string('last_name', 150)->nullable()->after('first_name');
            $table->string('middle_name', 150)->nullable()->after('last_name');
            $table->string('suffix', 32)->nullable()->after('middle_name');
        });
    }

    public function down(): void
    {
        Schema::table('research_authors', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'middle_name', 'suffix']);
        });
    }
};

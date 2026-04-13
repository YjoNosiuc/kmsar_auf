<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MySQL cannot cast ENUM directly to JSON; copy values into JSON arrays first.
     */
    public function up(): void
    {
        Schema::table('research', function (Blueprint $table) {
            $table->json('expected_output_tmp')->nullable();
        });

        DB::statement('UPDATE research SET expected_output_tmp = JSON_ARRAY(expected_output)');

        Schema::table('research', function (Blueprint $table) {
            $table->dropColumn('expected_output');
        });

        Schema::table('research', function (Blueprint $table) {
            $table->renameColumn('expected_output_tmp', 'expected_output');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research', function (Blueprint $table) {
            $table->enum('expected_output_old', ['publication', 'patent', 'policy_brief', 'other'])->nullable();
        });

        DB::statement('
            UPDATE research
            SET expected_output_old = JSON_UNQUOTE(JSON_EXTRACT(expected_output, "$[0]"))
            WHERE expected_output IS NOT NULL
        ');

        Schema::table('research', function (Blueprint $table) {
            $table->dropColumn('expected_output');
        });

        Schema::table('research', function (Blueprint $table) {
            $table->renameColumn('expected_output_old', 'expected_output');
        });
    }
};

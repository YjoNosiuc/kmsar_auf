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
        Schema::table('research', function (Blueprint $table) {
            $constraint = collect(DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_NAME = 'research'
                 AND COLUMN_NAME = 'other_college_id'
                 AND TABLE_SCHEMA = DATABASE()
                 AND REFERENCED_TABLE_NAME IS NOT NULL"
            ))->first();

            if ($constraint !== null) {
                DB::statement('ALTER TABLE research DROP FOREIGN KEY '.$constraint->CONSTRAINT_NAME);
            }

            foreach (DB::select("SHOW INDEX FROM research WHERE Column_name = 'other_college_id'") as $index) {
                if ($index->Key_name === 'PRIMARY') {
                    continue;
                }

                DB::statement('ALTER TABLE research DROP INDEX `'.$index->Key_name.'`');
            }

            DB::statement('ALTER TABLE research MODIFY COLUMN other_college_id JSON NULL');
            DB::statement("UPDATE research SET other_college_id = JSON_ARRAY(other_college_id) WHERE other_college_id IS NOT NULL AND JSON_TYPE(other_college_id) != 'ARRAY'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research', function (Blueprint $table) {
            DB::statement('UPDATE research SET other_college_id = JSON_UNQUOTE(JSON_EXTRACT(other_college_id, "$[0]")) WHERE other_college_id IS NOT NULL');
            DB::statement('ALTER TABLE research MODIFY COLUMN other_college_id BIGINT UNSIGNED NULL');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recreate the sessions table when it is missing but the base migration is already recorded.
 * This can happen after an interrupted migrate:fresh or a partial database reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        // Intentionally no-op: do not drop sessions on rollback of this repair migration.
    }
};

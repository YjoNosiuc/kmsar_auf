<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->string('preset', 40)->default('mailtrap_sandbox');
            $table->string('mail_mailer', 20)->default('smtp');
            $table->string('mail_host', 255);
            $table->unsignedSmallInteger('mail_port')->default(587);
            $table->string('mail_username', 255)->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_encryption', 10)->nullable();
            $table->string('mail_from_address', 255);
            $table->string('mail_from_name', 100);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_settings');
    }
};

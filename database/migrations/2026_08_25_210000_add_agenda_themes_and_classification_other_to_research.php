<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research', function (Blueprint $table) {
            if (! Schema::hasColumn('research', 'research_classification_other')) {
                $table->string('research_classification_other', 500)->nullable()->after('research_classification');
            }
            if (! Schema::hasColumn('research', 'agenda_themes')) {
                $table->json('agenda_themes')->nullable()->after('sdg_tags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('research', function (Blueprint $table) {
            if (Schema::hasColumn('research', 'research_classification_other')) {
                $table->dropColumn('research_classification_other');
            }
            if (Schema::hasColumn('research', 'agenda_themes')) {
                $table->dropColumn('agenda_themes');
            }
        });
    }
};

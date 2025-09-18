<?php

// database/migrations/2025_09_18_100000_alter_lotti_add_descrizione_and_unique.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('aziende_sanitarie_lotti', function (Blueprint $table) {
            if (!Schema::hasColumn('aziende_sanitarie_lotti', 'descrizione')) {
                $table->text('descrizione')->nullable()->after('nomeLotto');
            }
            // unique per azienda+nome
            $table->unique(['idAziendaSanitaria','nomeLotto'], 'lotti_azsani_nome_unique');
        });
    }

    public function down(): void {
        Schema::table('aziende_sanitarie_lotti', function (Blueprint $table) {
            $table->dropUnique('lotti_azsani_nome_unique');
            if (Schema::hasColumn('aziende_sanitarie_lotti', 'descrizione')) {
                $table->dropColumn('descrizione');
            }
        });
    }
};

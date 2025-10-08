<?php
// database/migrations/2025_01_01_100000_add_cap_to_associazioni_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->string('cap', 10)->nullable()->after('citta');
        });

        // (opzionale) prova un backfill veloce se hai tabella CAP (adatta i nomi tabella/colonne)
        // Esempio: tabella 'ter_cap' con campi: cap, denominazione_ita, sigla_provincia
        if (Schema::hasTable('ter_cap')) {
            DB::statement("
                UPDATE associazioni a
                JOIN ter_cap t
                  ON t.denominazione_ita = a.citta
                 AND t.sigla_provincia = a.provincia
                SET a.cap = t.cap
            ");
        }
    }

    public function down(): void
    {
        Schema::table('associazioni', function (Blueprint $table) {
            $table->dropColumn('cap');
        });
    }
};

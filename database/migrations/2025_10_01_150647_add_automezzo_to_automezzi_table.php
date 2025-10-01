<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Aggiunge la colonna se non esiste già
        if (!Schema::hasColumn('automezzi', 'Automezzo')) {
            Schema::table('Automezzi', function (Blueprint $table) {
                $table->string('Automezzo', 255)
                    ->nullable()
                    ->after('CodiceIdentificativo')
                    ->index();
            });

            // Backfill: "TARGA - CODICE" (salta parti vuote/null)
            // CONCAT_WS salta i NULL; NULLIF('', '') -> NULL così eviti doppio separatore
            DB::table('automezzi')->update([
                'Automezzo' => DB::raw("TRIM(CONCAT_WS(' - ', Targa, NULLIF(CodiceIdentificativo, '')))"),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('automezzi', 'Automezzo')) {
            Schema::table('automezzi', function (Blueprint $table) {
                $table->dropIndex(['Automezzo']); // nel dubbio, rimuovi l’indice
                $table->dropColumn('Automezzo');
            });
        }
    }
};

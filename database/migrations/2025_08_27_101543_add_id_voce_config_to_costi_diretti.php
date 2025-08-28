<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('costi_diretti', function (Blueprint $table) {
            // aggiungo la colonna, per ora nullable per permettere il backfill
            $table->unsignedBigInteger('idVoceConfig')->nullable()->after('idSezione');
            $table->index('idVoceConfig', 'costi_diretti_idVoceConfig_idx');
        });

        // BACKFILL: mappa per descrizione (normalizzata) -> idVoceConfig
        // NB: case/space insensitive; adatta le funzioni se usi un DB diverso da MySQL
        DB::statement("
            UPDATE costi_diretti cd
            JOIN (
                SELECT id, UPPER(TRIM(REPLACE(REPLACE(descrizione, CHAR(13), ''), CHAR(10), ''))) AS norm_desc
                FROM riepilogo_voci_config
            ) vc ON UPPER(TRIM(REPLACE(REPLACE(cd.voce, CHAR(13), ''), CHAR(10), ''))) = vc.norm_desc
            SET cd.idVoceConfig = vc.id
            WHERE cd.idVoceConfig IS NULL
        ");

        // opzionale: se vuoi rendere la FK NOT NULL solo se tutti mappati
        $missing = DB::table('costi_diretti')->whereNull('idVoceConfig')->count();
        if ($missing === 0) {
            Schema::table('costi_diretti', function (Blueprint $table) {
                $table->unsignedBigInteger('idVoceConfig')->nullable(false)->change();
            });
        }

        // vincolo di FK
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->foreign('idVoceConfig', 'costi_diretti_idVoceConfig_fk')
                  ->references('id')->on('riepilogo_voci_config')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('costi_diretti', function (Blueprint $table) {
            $table->dropForeign('costi_diretti_idVoceConfig_fk');
            $table->dropIndex('costi_diretti_idVoceConfig_idx');
            $table->dropColumn('idVoceConfig');
        });
    }
};
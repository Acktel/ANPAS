<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Se il DB è già popolato con tipologia/anno, salta
        if (Schema::hasColumn('riepilogo_dati', 'idAnno')) {
            return;
        }

        // Disabilitiamo i vincoli per evitare errori temporanei
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::table('riepilogo_dati', function (Blueprint $table) {
            // Aggiungo le colonne solo se non esistono
            if (! Schema::hasColumn('riepilogo_dati', 'idAnno')) {
                $table->unsignedInteger('idAnno')
                      ->after('idRiepilogo');
            }

            if (! Schema::hasColumn('riepilogo_dati', 'idTipologiaRiepilogo')) {
                $table->unsignedBigInteger('idTipologiaRiepilogo')
                      ->after('idAnno');

                $table->foreign('idTipologiaRiepilogo')
                      ->references('id')
                      ->on('tipologia_riepilogo')
                      ->cascadeOnUpdate()
                      ->restrictOnDelete();
            }

            // Aggiungo l’indice composito
            $table->index(
                ['idRiepilogo', 'idAnno', 'idTipologiaRiepilogo'],
                'riepilogo_tipo_anno_idx'
            );
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void {
        Schema::table('riepilogo_dati', function (Blueprint $table) {
            if (Schema::hasIndex('riepilogo_dati', 'riepilogo_tipo_anno_idx')) {
                $table->dropIndex('riepilogo_tipo_anno_idx');
            }

            if (Schema::hasColumn('riepilogo_dati', 'idTipologiaRiepilogo')) {
                $table->dropForeign(['idTipologiaRiepilogo']);
            }

            $cols = array_filter(
                ['idAnno', 'idTipologiaRiepilogo'],
                fn($col) => Schema::hasColumn('riepilogo_dati', $col)
            );

            if (! empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

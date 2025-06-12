<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('riepilogo_dati', function (Blueprint $table) {
            // 👇 Inserisce idAnno dopo idRiepilogo
            $table->unsignedInteger('idAnno')->after('idRiepilogo');

            // 👇 Inserisce idTipologiaRiepilogo dopo idAnno
            $table->unsignedBigInteger('idTipologiaRiepilogo')->after('idAnno');

            // 🛡️ Foreign key su tipologia_riepilogo
            $table->foreign('idTipologiaRiepilogo')
                ->references('id')
                ->on('tipologia_riepilogo')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // ⚙️ Indice composito per ottimizzare le query
            $table->index(['idRiepilogo', 'idAnno', 'idTipologiaRiepilogo'], 'riepilogo_tipo_anno_idx');
        });
    }

    public function down(): void {
        Schema::table('riepilogo_dati', function (Blueprint $table) {
            $table->dropForeign(['idTipologiaRiepilogo']);
            $table->dropIndex('riepilogo_tipo_anno_idx');
            $table->dropColumn(['idAnno', 'idTipologiaRiepilogo']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('automezzi_km', function (Blueprint $table) {
            // Flag per nominare il mezzo TITOLARE sulla convenzione
            $table->boolean('is_titolare')
                ->default(false)
                ->after('KMPercorsi')
                ->comment('1 = mezzo titolare per la convenzione');

            /**
             * Emulazione "partial unique":
             * se is_titolare = 1 => tit_conv = idConvenzione, altrimenti NULL.
             * L’unicità su tit_conv garantisce max 1 titolare per convenzione.
             * Se il tuo MySQL non supporta colonne generate VIRTUAL, usa storedAs().
             */
            $table->unsignedBigInteger('tit_conv')->virtualAs('IF(is_titolare, idConvenzione, NULL)');
            $table->unique(['tit_conv'], 'uniq_titolare_per_convenzione');

            // Indici utili
            $table->index(['idConvenzione'], 'idx_autkm_conv');
            $table->index(['idAutomezzo'], 'idx_autkm_mezzo');
        });
    }

    public function down(): void
    {
        Schema::table('automezzi_km', function (Blueprint $table) {
            $table->dropUnique('uniq_titolare_per_convenzione');
            $table->dropIndex('idx_autkm_conv');
            $table->dropIndex('idx_autkm_mezzo');
            $table->dropColumn(['is_titolare','tit_conv']);
        });
    }
};

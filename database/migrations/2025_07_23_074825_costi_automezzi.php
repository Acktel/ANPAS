<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('costi_automezzi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idAutomezzo')->constrained('automezzi', 'idAutomezzo')->cascadeOnDelete();
            $table->foreignId('idAnno')->constrained('anni', 'idAnno')->cascadeOnDelete();

            $table->decimal('LeasingNoleggio', 12, 2)->default(0);
            $table->decimal('Assicurazione', 12, 2)->default(0);
            $table->decimal('ManutenzioneOrdinaria', 12, 2)->default(0);
            $table->decimal('ManutenzioneStraordinaria', 12, 2)->default(0);
            $table->decimal('RimborsiAssicurazione', 12, 2)->default(0);
            $table->decimal('PuliziaDisinfezione', 12, 2)->default(0);
            $table->decimal('Carburanti', 12, 2)->default(0);
            $table->decimal('Additivi', 12, 2)->default(0);
            $table->decimal('RimborsiUTF', 12, 2)->default(0);
            $table->decimal('InteressiPassivi', 12, 2)->default(0);
            $table->decimal('AltriCostiMezzi', 12, 2)->default(0);
            $table->decimal('ManutenzioneSanitaria', 12, 2)->default(0);
            $table->decimal('LeasingSanitaria', 12, 2)->default(0);
            $table->decimal('AmmortamentoMezzi', 12, 2)->default(0);
            $table->decimal('AmmortamentoSanitaria', 12, 2)->default(0);

            $table->timestamps();
            $table->unique(['idAutomezzo', 'idAnno'], 'automezzo_anno_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('costi_automezzi');
    }
};

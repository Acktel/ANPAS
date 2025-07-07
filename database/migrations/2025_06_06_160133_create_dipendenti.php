<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // 6. Tabella dipendenti
        Schema::create('dipendenti', function (Blueprint $table) {
            $table->id('idDipendente');
            $table->foreignId('idAssociazione')
                ->constrained('associazioni', 'idAssociazione')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('idAnno')
                ->constrained('anni', 'idAnno')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // Nome e Cognome separati
            $table->string('DipendenteNome', 100);
            $table->string('DipendenteCognome', 100);

            // Nuovi campi richiesti
            $table->string('Qualifica', 100);
            $table->string('ContrattoApplicato', 100);
            $table->string('LivelloMansione', 100);

            $table->timestamps();

            // Eventuale indice composto (se necessario per ricerche frequenti)
            $table->index(['idAssociazione', 'idAnno'], 'dipendenti_associazione_anno_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('dipendenti');
        Schema::enableForeignKeyConstraints();
    }
};

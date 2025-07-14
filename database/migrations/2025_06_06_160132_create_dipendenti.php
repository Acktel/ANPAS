<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dipendenti', function (Blueprint $table) {
            $table->id('idDipendente');

            // FK su associazioni
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            // FK su anni
            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            // Dati anagrafici
            $table->string('DipendenteNome', 100);
            $table->string('DipendenteCognome', 100);

            // Contratto e livello mansione
            $table->string('ContrattoApplicato', 100);

            // Tracking utenti
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['idAssociazione', 'idAnno'], 'dip_assoc_anno_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dipendenti');
    }
};

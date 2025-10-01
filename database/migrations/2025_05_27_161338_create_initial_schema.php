<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabella anni
        Schema::create('anni', function (Blueprint $table) {
            $table->id('idAnno');
            $table->string('Anno', 4);
            $table->timestamps();
        });

        // 2. Tabella associazioni
        Schema::create('associazioni', function (Blueprint $table) {
            $table->id('idAssociazione');
            $table->string('Associazione', 100);
            $table->timestamps();
        });

        // 3. Tabella automezzi
        Schema::create('automezzi', function (Blueprint $table) {
            $table->id('idAutomezzo');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete();
            $table->timestamps();
        });

        // 4. Tabella convenzioni
        Schema::create('convenzioni', function (Blueprint $table) {
            $table->id('idConvenzione');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete();
            $table->string('Convenzione', 100);
            $table->timestamps();
        });

        // 5. Tabella automezzi_km
        Schema::create('automezzi_km', function (Blueprint $table) {
            $table->id('idAutomezzoKM');
            $table->foreignId('idAutomezzo')
                  ->constrained('automezzi', 'idAutomezzo')
                  ->cascadeOnDelete();
            $table->foreignId('idConvenzione')
                  ->constrained('convenzioni', 'idConvenzione')
                  ->cascadeOnDelete();
            $table->integer('KMPercorsi');
            $table->timestamps();
        });

        // 6. Tabella dipendenti
        Schema::create('dipendenti', function (Blueprint $table) {
            $table->id('idDipendente');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete();
            $table->string('Dipendente', 100);
            $table->timestamps();
        });

        // 7. Tabella costi_tipo
        Schema::create('costi_tipo', function (Blueprint $table) {
            $table->id('idTipo');
            $table->string('Tipo', 100);
            $table->string('Form', 50);
            $table->timestamps();
        });

        // 8. Tabella costi_gruppi
        Schema::create('costi_gruppi', function (Blueprint $table) {
            $table->id('idGruppo');
            $table->string('Gruppo', 100);
            $table->integer('Ordinamento');
            $table->timestamps();
        });

        // 9. Tabella costi_dettaglio
        Schema::create('costi_dettaglio', function (Blueprint $table) {
            $table->id('idCostoDettaglio');
            $table->decimal('Costo', 10, 2);
            $table->foreignId('idCostiGruppo')
                  ->constrained('costi_gruppi', 'idGruppo')
                  ->cascadeOnDelete();
            $table->integer('Ordinamento');
            $table->foreignId('idTipo')
                  ->constrained('costi_tipo', 'idTipo')
                  ->cascadeOnDelete();
            $table->string('TipoRipartizione', 50);
            $table->timestamps();
        });

        // 10. Tabella costi
        Schema::create('costi', function (Blueprint $table) {
            $table->id('idCosto');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete();
            $table->foreignId('idConvenzione')
                  ->constrained('convenzioni', 'idConvenzione')
                  ->cascadeOnDelete();
            $table->foreignId('idCostoDettaglio')
                  ->constrained('costi_dettaglio', 'idCostoDettaglio')
                  ->cascadeOnDelete();
            $table->decimal('Importo', 10, 2);
            $table->timestamps();
        });

        // 11. Tabella utenti
        Schema::create('utenti', function (Blueprint $table) {
            $table->id('idUtente');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->string('Utente', 100);
            $table->timestamps();
        });

        // 12. Tabella ruoli
        Schema::create('ruoli', function (Blueprint $table) {
            $table->id('idRuolo');
            $table->string('Ruolo', 50);
            $table->timestamps();
        });

        // 13. Tabella pivot utenti_ruolo
        Schema::create('utenti_ruolo', function (Blueprint $table) {
            $table->foreignId('idUtente')
                  ->constrained('utenti', 'idUtente')
                  ->cascadeOnDelete();
            $table->foreignId('idRuolo')
                  ->constrained('ruoli', 'idRuolo')
                  ->cascadeOnDelete();
            $table->primary(['idUtente', 'idRuolo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utenti_ruolo');
        Schema::dropIfExists('ruoli');
        Schema::dropIfExists('utenti');
        Schema::dropIfExists('costi');
        Schema::dropIfExists('costi_dettaglio');
        Schema::dropIfExists('costi_gruppi');
        Schema::dropIfExists('costi_tipo');
        Schema::dropIfExists('dipendenti');
        Schema::dropIfExists('automezzi_km');
        Schema::dropIfExists('convenzioni');
        Schema::dropIfExists('automezzi');
        Schema::dropIfExists('associazioni');
        Schema::dropIfExists('anni');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('costi_diretti', function (Blueprint $table) {
            $table->id('idCosto');

            // Chiavi esterne
            $table->foreignId('idAssociazione')
                ->constrained('associazioni', 'IdAssociazione')
                ->cascadeOnDelete();

            $table->foreignId('idConvenzione')
                ->constrained('convenzioni', 'idConvenzione')
                ->cascadeOnDelete();

            $table->foreignId('idAnno')
                ->constrained('anni', 'idAnno')
                ->cascadeOnDelete();

            // Dati specifici del costo
            $table->unsignedTinyInteger('idSezione'); // es. 2, 3, 4...
            $table->string('voce', 255); // es. "Assicurazioni", "Carburante"

            $table->decimal('costo', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['idAssociazione', 'idAnno', 'idConvenzione', 'idSezione', 'voce'],
                'unique_costo_diretto_per_voce'
            );
        });
    }

    public function down(): void {
        Schema::dropIfExists('costi_diretti');
    }
};

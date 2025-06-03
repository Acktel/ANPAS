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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('costi');
    }
};

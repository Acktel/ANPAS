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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('costi_dettaglio');
        Schema::enableForeignKeyConstraints();
    }
};

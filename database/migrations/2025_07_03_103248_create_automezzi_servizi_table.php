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
        Schema::create('automezzi_servizi', function (Blueprint $table) {
            $table->id('idAutomezzoServizio');
            
            $table->foreignId('idAutomezzo')
                  ->constrained('automezzi', 'idAutomezzo')
                  ->cascadeOnDelete();
            
            $table->foreignId('idConvenzione')
                  ->constrained('convenzioni', 'idConvenzione')
                  ->cascadeOnDelete();

            $table->integer('NumeroServizi')->default(0);
            
            $table->timestamps();

            // Unicità per evitare duplicati
            $table->unique(['idAutomezzo', 'idConvenzione']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automezzi_servizi');
    }
};

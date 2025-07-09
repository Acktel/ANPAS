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
        Schema::create('dipendenti_servizi', function (Blueprint $table) {
            // PK
            $table->id('idDipendenteServizio');

            // FK sul dipendente
            $table->foreignId('idDipendente')
                  ->constrained('dipendenti', 'idDipendente')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // FK sulla convenzione
            $table->foreignId('idConvenzione')
                  ->constrained('convenzioni', 'idConvenzione')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            // Ore di servizio per quella convenzione
            $table->integer('OreServizio')->default(0);

            $table->timestamps();

            // Unicità per evitare duplicati di (dipendente, convenzione)
            $table->unique(
                ['idDipendente', 'idConvenzione'],
                'dip_srv_dip_conv_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // Se servisse rimuovere a mano le FK:
        Schema::table('dipendenti_servizi', function (Blueprint $table) {
            $table->dropForeign(['idDipendente']);
            $table->dropForeign(['idConvenzione']);
        });

        Schema::dropIfExists('dipendenti_servizi');

        Schema::enableForeignKeyConstraints();
    }
};

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
        Schema::create('mezzi_sostitutivi', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Convenzione e idAnno a cui si riferisce il costo
            $table->unsignedBigInteger('idConvenzione');
            $table->unsignedInteger('idAnno');

            // Valore impostato dall’utente
            $table->decimal('costo_fascia_oraria', 15, 2)->default(0);

            $table->timestamps();

            // Vincoli / indici
            $table->foreign('idConvenzione')
                  ->references('idConvenzione')->on('convenzioni')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            // Una sola riga per convenzione+idAnno
            $table->unique(['idConvenzione', 'idAnno'], 'uniq_conv_idAnno_mezzi_sost');
            $table->index('idAnno');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mezzi_sostitutivi');
    }
};

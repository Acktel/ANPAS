<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */

    public function up(): void {
        Schema::create('azienda_sanitaria_convenzione', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idAziendaSanitaria');
            $table->unsignedBigInteger('idConvenzione');

            $table->foreign('idAziendaSanitaria')->references('idAziendaSanitaria')->on('aziende_sanitarie')->cascadeOnDelete();
            $table->foreign('idConvenzione')->references('idConvenzione')->on('convenzioni')->cascadeOnDelete();

            $table->timestamps();
            $table->unique(['idAziendaSanitaria', 'idConvenzione'], 'azienda_convenzione_unique');
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('azienda_sanitaria_convenzione');
    }
};

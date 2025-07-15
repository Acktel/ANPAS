<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dipendenti_livelli_mansione', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idDipendente');
            $table->unsignedBigInteger('idLivelloMansione');

            $table->foreign('idDipendente')->references('idDipendente')->on('dipendenti')->onDelete('cascade');
            $table->foreign('idLivelloMansione')->references('id')->on('livello_mansione')->onDelete('cascade');

            $table->unique(['idDipendente', 'idLivelloMansione'], 'uniq_dip_liv');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('dipendenti_livelli_mansione');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('aziende_sanitarie_lotti', function (Blueprint $table) {
            $table->id();
            $table->string('nomeLotto');
            $table->unsignedBigInteger('idAziendaSanitaria');
            $table->timestamps();

            $table->foreign('idAziendaSanitaria')
                  ->references('idAziendaSanitaria')
                  ->on('aziende_sanitarie')
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('aziende_sanitarie_lotti');
    }
};

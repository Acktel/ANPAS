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
        Schema::create('aziende_sanitarie', function (Blueprint $table) {
            $table->id('idAziendaSanitaria');
            $table->string('Nome', 150);
            $table->string('Indirizzo')->nullable();
            $table->string('mail')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aziende_sanitarie');
    }
};

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
        Schema::create('ter_provinces', function (Blueprint $table) {
            $table->string('codice_regione', 4);
            $table->string('sigla_provincia', 4)->primary();
            $table->string('denominazione_provincia', 50);
            $table->string('tipologia_provincia', 100);
            $table->integer('numero_comuni');
            $table->decimal('superficie_kmq', 10,4);
            $table->string('codice_sovracomunale', 6)->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ter_provinces');
    }
};

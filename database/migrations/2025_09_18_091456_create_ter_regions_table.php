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
        Schema::create('ter_regions', function (Blueprint $table) {
            $table->string('ripartizione_geografica', 20);
            $table->string('codice_regione', 4)->primary();
            $table->string('denominazione_regione', 50);
            $table->string('tipologia_regione', 30);
            $table->integer('numero_province');
            $table->integer('numero_comuni');
            $table->decimal('superficie_kmq',10,4);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ter_regions');
    }
};

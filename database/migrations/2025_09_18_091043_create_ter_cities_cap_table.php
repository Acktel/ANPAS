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
        Schema::create('ter_cities_cap', function (Blueprint $table) {
            $table->string('codice_istat', 12);
            $table->string('denominazione_ita_altra', 191);
            $table->string('denominazione_ita', 191);
            $table->string('denominazione_altra', 191);
            $table->string('cap', 10);
            $table->string('sigla_provincia', 4);
            $table->string('denominazione_provincia', 50);
            $table->string('tipologia_provincia', 100);
            $table->string('codice_regione', 4);
            $table->string('denominazione_regione', 50);
            $table->string('tipologia_regione', 30);
            $table->string('ripartizione_geografica', 20);
            $table->string('flag_capoluogo', 4);
            $table->string('codice_belfiore', 8);
            $table->decimal('lat', 13, 7);
            $table->decimal('lon', 13, 7);
            $table->decimal('superficie_kmq', 10, 4);

            $table->primary(['codice_istat', 'cap']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ter_cities_cap');
    }
};

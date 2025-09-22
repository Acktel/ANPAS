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
        Schema::create('ter_city_validity', function (Blueprint $table) {
            $table->string('sigla_provincia', 4);
            $table->string('codice_istat', 12);
            $table->string('denominazione_ita', 100);
            $table->string('codice_belfiore', 8);
            $table->date('data_inizio_validita');
            $table->date('data_fine_validita')->nullable()->default(NULL);
            $table->string('stato_validita', 16);

            $table->primary(['codice_istat', 'codice_belfiore', 'data_inizio_validita']);  
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ter_city_validity');
    }
};

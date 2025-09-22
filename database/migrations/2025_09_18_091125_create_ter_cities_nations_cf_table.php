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
        Schema::create('ter_cities_nations_cf', function (Blueprint $table) {
            $table->string('sigla_provincia', 4);
            $table->string('denominazione_ita', 100)->index();
            $table->string('codice_belfiore', 8)->index();
            $table->date('data_inizio_validita')->nullable()->default(NULL);
            $table->date('data_fine_validita')->nullable()->default(NULL);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ter_cities_nations_cf');
    }
};

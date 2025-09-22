<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ter_nations', function (Blueprint $table) {
            $table->string('sigla_nazione', 6)->primary();
            $table->string('codice_belfiore', 8)->unique();
            $table->string('denominazione_nazione', 100);
            $table->string('denominazione_cittadinanza', 100);
            $table->string('denominazione_continente', 50)->nullable();
            $table->string('denominazione_area', 100)->nullable();
            $table->string('codice_iso_2', 2)->unique()->nullable();
            $table->string('codice_iso_3', 3)->unique()->nullable();
            $table->string('codice_istat', 5)->unique()->nullable();
            $table->string('codice_min', 5)->unique()->nullable();
            $table->string('codice_unsd_m49', 5)->unique()->nullable();
            $table->boolean('active')->default(true);
        });
    }

    public function down()
    {
        Schema::dropIfExists('ter_nations');
    }
};

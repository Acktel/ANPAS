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
        Schema::create('dipendenti_qualifiche', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('idDipendente');
            $table->unsignedBigInteger('idQualifica');

            // Foreign keys
            $table->foreign('idDipendente')
                  ->references('idDipendente')
                  ->on('dipendenti')
                  ->onDelete('cascade');

            $table->foreign('idQualifica')
                  ->references('id')
                  ->on('qualifiche')
                  ->onDelete('cascade');

            // Evita duplicati
            $table->unique(['idDipendente', 'idQualifica']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dipendenti_qualifiche');
    }
};

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
        // 6. Tabella dipendenti
        Schema::create('dipendenti', function (Blueprint $table) {
            $table->id('idDipendente');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete();
            $table->string('Dipendente', 100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dipendenti');
    }
};

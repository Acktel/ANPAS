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
        // 3. Tabella automezzi
        Schema::create('automezzi', function (Blueprint $table) {
            $table->id('idAutomezzo');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete();
            $table->string('Automezzo', 100);
            $table->string('Targa', 20);
            $table->string('CodiceIdentificativo', 50)->nullable();
            $table->year('AnnoPrimaImmatricolazione')->nullable();
            $table->string('Modello', 100);
            $table->string('TipoVeicolo', 50);
            $table->integer('KmRiferimento')->default(0);
            $table->integer('KmTotali')->default(0);
            $table->string('TipoCarburante', 50);
            $table->date('DataUltimaAutorizzazioneSanitaria')->nullable();
            $table->date('DataUltimoCollaudo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automezzi');
    }
};

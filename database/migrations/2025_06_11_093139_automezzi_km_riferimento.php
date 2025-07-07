<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('automezzi_km_riferimento', function (Blueprint $table) {
            $table->id('idAutomezzoKmRif');
            $table->foreignId('idAutomezzo')
                  ->constrained('automezzi', 'idAutomezzo')
                  ->cascadeOnDelete();

            $table->foreignId('idAnno')
                  ->constrained('anni', 'idAnno')
                  ->cascadeOnDelete();

            $table->integer('KmRiferimento')->default(0);

            $table->timestamps();

            $table->unique(['idAutomezzo', 'idAnno'], 'automezzo_anno_unique');
        });
    }

    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('automezzi_km_riferimento');
        Schema::enableForeignKeyConstraints();
    }
};

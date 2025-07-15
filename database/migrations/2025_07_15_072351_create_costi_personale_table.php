<?php

// database/migrations/2025_07_15_000000_create_costi_personale_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('costi_personale', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idDipendente');
            $table->decimal('Retribuzioni', 12, 2)->default(0);
            $table->decimal('OneriSociali', 12, 2)->default(0);
            $table->decimal('TFR', 12, 2)->default(0);
            $table->decimal('Consulenze', 12, 2)->default(0);
            $table->decimal('Totale', 12, 2)->default(0);
            $table->integer('idAnno');

            $table->timestamps();

            $table->foreign('idDipendente')->references('idDipendente')->on('dipendenti')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('costi_personale');
    }
};

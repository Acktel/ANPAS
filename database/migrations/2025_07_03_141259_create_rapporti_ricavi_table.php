<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rapporti_ricavi', function (Blueprint $table) {
            $table->id();

            // Chiavi esterne
            $table->unsignedBigInteger('idConvenzione');
            $table->unsignedBigInteger('idAnno');
            $table->unsignedBigInteger('idAssociazione')->nullable(); // opzionale

            // Dati
            $table->decimal('rimborso', 12, 2)->default(0.00);

            // Timestamps
            $table->timestamps();

            // Unicità
            $table->unique(['idConvenzione', 'idAnno', 'idAssociazione'], 'unique_rapporto');
        });
    }

    public function down(): void
    {
        
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('rapporti_ricavi');
        Schema::enableForeignKeyConstraints();
    }
};

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
        Schema::create('riepilogo_dati', function (Blueprint $table) {
            // PK auto‐increment
            $table->id();

            // FK verso riepiloghi.idRiepilogo
            $table->foreignId('idRiepilogo')
                  ->constrained('riepiloghi', 'idRiepilogo')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            // Colonne dati:
            $table->string('descrizione', 500);
            $table->decimal('preventivo', 12, 2)->default(0.00);
            $table->decimal('consuntivo', 12, 2)->default(0.00);

            // timestamps (created_at, updated_at)
            $table->timestamps();

            // Index su idRiepilogo per velocizzare le query
            $table->index('idRiepilogo', 'riepilogo_dati_riepilogo_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riepilogo_dati');
    }
};

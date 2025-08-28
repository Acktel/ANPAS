<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // butto giù la vecchia tabella se esiste
        Schema::dropIfExists('riepilogo_dati');

        // ricreo con la nuova struttura
        Schema::create('riepilogo_dati', function (Blueprint $t) {
            $t->id();

            // Riepilogo = pivot Associazione + Anno
            $t->foreignId('idRiepilogo')
              ->constrained('riepiloghi', 'idRiepilogo')
              ->cascadeOnDelete();

            // Voce di configurazione
            $t->foreignId('idVoceConfig')
              ->constrained('riepilogo_voci_config', 'id')
              ->cascadeOnDelete();

            // Convenzione specifica (null = non legata a convenzione)
            $t->unsignedBigInteger('idConvenzione')->nullable()->index();

            // Valori numerici
            $t->decimal('preventivo', 12, 2)->default(0);
            $t->decimal('consuntivo', 12, 2)->default(0);

            $t->timestamps();

            // vincolo per evitare duplicati della stessa voce in stessa convenzione
            $t->unique(['idRiepilogo','idVoceConfig','idConvenzione'], 'uniq_riep_voce_conv');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riepilogo_dati');
    }
};

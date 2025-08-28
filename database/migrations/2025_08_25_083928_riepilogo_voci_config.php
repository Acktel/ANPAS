// database/migrations/2025_08_25_000000_create_riepilogo_voci_config_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('riepilogo_voci_config', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('idTipologiaRiepilogo'); // FK logica a tipologia_riepilogo.id
            $t->string('descrizione', 500);
            $t->unsignedSmallInteger('ordinamento')->default(0);
            $t->boolean('attivo')->default(true);
            $t->timestamps();

            $t->index(['idTipologiaRiepilogo', 'ordinamento']);
            $t->unique(['idTipologiaRiepilogo', 'descrizione'], 'uniq_tipologia_descrizione');
        });
    }

    public function down(): void {
        Schema::dropIfExists('riepilogo_voci_config');
    }
};

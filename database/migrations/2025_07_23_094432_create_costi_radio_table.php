<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('costi_radio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('idAssociazione');
            $table->integer('idAnno');

            $table->decimal('ManutenzioneApparatiRadio', 12, 2)->default(0);
            $table->decimal('MontaggioSmontaggioRadio118', 12, 2)->default(0);
            $table->decimal('LocazionePonteRadio', 12, 2)->default(0);
            $table->decimal('AmmortamentoImpiantiRadio', 12, 2)->default(0);

            $table->timestamps();

            $table->foreign('idAssociazione')->references('IdAssociazione')->on('associazioni')->onDelete('cascade');
            $table->unique(['idAssociazione', 'idAnno']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('costi_radio');
    }
};

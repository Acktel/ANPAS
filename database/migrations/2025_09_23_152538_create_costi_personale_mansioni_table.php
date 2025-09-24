<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('costi_personale_mansioni', function (Blueprint $t) {
            $t->increments('id'); // PK locale (INT UNSIGNED)

            // ⚠️ Allinea i tipi alle tabelle padre
            $t->unsignedInteger('idDipendente');   // dipendenti.idDipendente presumibilmente INT UNSIGNED
            $t->unsignedInteger('idQualifica');    // qualifiche.id INT UNSIGNED
            $t->unsignedBigInteger('idAnno');      // anni.idAnno BIGINT UNSIGNED  (dato il tuo migration)

            $t->decimal('percentuale', 5, 2)->default(0); // 0..100
            $t->timestamps();

            // Evita duplicati della stessa mansione nello stesso anno
            $t->unique(['idDipendente','idQualifica','idAnno'], 'uq_cpm_tripla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costi_personale_mansioni');
    }
};

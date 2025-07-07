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
        Schema::create('riepiloghi', function (Blueprint $table) {
            // PK: idRiepilogo
            $table->id('idRiepilogo');

            // FK verso associazioni.idAssociazione
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

           $table->unsignedBigInteger('idAnno');

            // timestamps (created_at, updated_at)
            $table->timestamps();

            // Index su (idAssociazione, idAnno) per eventuali ricerche/filter
            $table->index(['idAssociazione', 'idAnno'], 'riepiloghi_associazione_anno_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('riepiloghi');
        Schema::enableForeignKeyConstraints();
    }
};

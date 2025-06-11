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
        Schema::create('convenzioni', function (Blueprint $table) {
            $table->id('idConvenzione');
            $table->foreignId('idAssociazione')
                  ->constrained('associazioni', 'idAssociazione')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('idAnno');
            $table->string('Convenzione', 100);
            $table->string('lettera_identificativa', 5)
                ->default('')
                ->comment('Lettera di identificazione del servizio / convenzione');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convenzioni');
    }
};

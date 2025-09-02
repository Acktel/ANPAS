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
        Schema::create('convenzioni_materiale_sanitario', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('idConvenzione')
                ->constrained('convenzioni', 'idConvenzione')
                ->cascadeOnDelete();

            $table->foreignId('idMaterialeSanitario')
                ->constrained('materiale_sanitario', 'id')
                ->cascadeOnDelete();

            $table->timestamps();

            // Nome personalizzato per evitare limite di MySQL
            $table->unique(['idConvenzione', 'idMaterialeSanitario'], 'conv_mat_sanitario_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('convenzioni_materiale_sanitario');
        Schema::enableForeignKeyConstraints();
    }
};

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
        // 5. Tabella automezzi_km
        Schema::create('automezzi_km', function (Blueprint $table) {
            $table->id('idAutomezzoKM');
            $table->foreignId('idAutomezzo')
                  ->constrained('automezzi', 'idAutomezzo')
                  ->cascadeOnDelete();
            $table->foreignId('idConvenzione')
                  ->constrained('convenzioni', 'idConvenzione')
                  ->cascadeOnDelete();
            $table->integer('KMPercorsi');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('automezzi_km');
        Schema::enableForeignKeyConstraints();
    }
};

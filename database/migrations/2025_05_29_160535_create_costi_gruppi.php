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
        // 8. Tabella costi_gruppi
        Schema::create('costi_gruppi', function (Blueprint $table) {
            $table->id('idGruppo');
            $table->string('Gruppo', 100);
            $table->integer('Ordinamento');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('costi_gruppi');
        Schema::enableForeignKeyConstraints();
    }
};

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
        Schema::create('costi_tipo', function (Blueprint $table) {
            $table->id('idTipo');
            $table->string('Tipo', 100);
            $table->string('Form', 50);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('costi_tipo');
        Schema::enableForeignKeyConstraints();
    }
};

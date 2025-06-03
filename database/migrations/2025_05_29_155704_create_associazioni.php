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
        // 2. Tabella associazioni
        Schema::create('associazioni', function (Blueprint $table) {
            $table->id('IdAssociazione');
            $table->string('Associazione', 100);
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('provincia');
            $table->string('citta', 100);     
            $table->boolean('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('associazioni');
    }
};

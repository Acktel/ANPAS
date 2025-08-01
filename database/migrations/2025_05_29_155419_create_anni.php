<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        // 1. Tabella anni
        Schema::create('anni', function (Blueprint $table) {
            $table->id('idAnno');
            $table->string('Anno', 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('anni');
        Schema::enableForeignKeyConstraints();
    }
};

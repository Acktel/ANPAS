<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tipologia_riepilogo', function (Blueprint $table) {
            $table->id();
            $table->string('descrizione', 255)->unique();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('tipologia_riepilogo');
        Schema::enableForeignKeyConstraints();
    }
};

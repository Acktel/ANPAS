<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('automezzi_km', function (Blueprint $table) {
            // idAutomezzoKM diventa decimal(10,2)
            $table->decimal('idAutomezzoKM', 10, 2)->change();

            // KMPercorsi diventa decimal(10,2) con default 0
            $table->decimal('KMPercorsi', 10, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automezzi_km', function (Blueprint $table) {
            $table->unsignedBigInteger('idAutomezzoKM')->change();
            $table->integer('KMPercorsi')->default(0)->change();
        });
    }
};

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
            // Riporto idAutomezzoKM a BIGINT UNSIGNED AUTO_INCREMENT
            $table->unsignedBigInteger('idAutomezzoKM')->autoIncrement()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automezzi_km', function (Blueprint $table) {
            $table->decimal('idAutomezzoKM', 10, 2)->change();
        });
    }
};

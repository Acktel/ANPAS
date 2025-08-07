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
        // Cambia KmRiferimento da integer a float
        Schema::table('automezzi_km_riferimento', function (Blueprint $table) {
            $table->decimal('KmRiferimento', 10, 2)
                  ->default(0)
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ripristina KmRiferimento come integer
        Schema::table('automezzi_km_riferimento', function (Blueprint $table) {
            $table->integer('KmRiferimento')
                  ->default(0)
                  ->change();
        });
    }
};
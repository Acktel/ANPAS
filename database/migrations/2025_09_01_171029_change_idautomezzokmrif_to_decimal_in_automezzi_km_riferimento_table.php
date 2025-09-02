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
        Schema::table('automezzi_km_riferimento', function (Blueprint $table) {
            $table->decimal('idAutomezzoKmRif', 10, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('automezzi_km_riferimento', function (Blueprint $table) {
            $table->unsignedBigInteger('idAutomezzoKmRif')->change();
        });
    }
};

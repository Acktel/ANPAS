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
        Schema::table('automezzi', function (Blueprint $table) {
            $table->unsignedBigInteger('idTipoVeicolo')->nullable()->after('Modello');
            $table->unsignedBigInteger('idTipoCarburante')->nullable()->after('KmTotali');
        });
    }

    public function down(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->dropColumn(['idTipoVeicolo', 'idTipoCarburante']);
        });
    }

};

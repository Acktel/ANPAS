<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            // solo se manca
            if (! Schema::hasColumn('automezzi', 'idTipoVeicolo')) {
                $table->foreignId('idTipoVeicolo')
                      ->nullable()
                      ->after('Modello')
                      ->constrained('vehicle_types')
                      ->cascadeOnDelete();
            }
            if (! Schema::hasColumn('automezzi', 'idTipoCarburante')) {
                $table->foreignId('idTipoCarburante')
                      ->nullable()
                      ->after('KmTotali')
                      ->constrained('fuel_types')
                      ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            if (Schema::hasColumn('automezzi', 'idTipoVeicolo')) {
                $table->dropForeign(['idTipoVeicolo']);
                $table->dropColumn('idTipoVeicolo');
            }
            if (Schema::hasColumn('automezzi', 'idTipoCarburante')) {
                $table->dropForeign(['idTipoCarburante']);
                $table->dropColumn('idTipoCarburante');
            }
        });
    }
};

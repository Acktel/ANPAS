<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToAutomezziTable extends Migration
{
    public function up(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->string('Targa', 20)->after('Automezzo');
            $table->string('CodiceIdentificativo', 50)->nullable()->after('Targa');
            $table->year('AnnoPrimaImmatricolazione')->nullable()->after('CodiceIdentificativo');
            $table->string('Modello', 100)->after('AnnoPrimaImmatricolazione');
            $table->string('TipoVeicolo', 50)->after('Modello');
            $table->integer('KmRiferimento')->default(0)->after('TipoVeicolo');
            $table->integer('KmTotali')->default(0)->after('KmRiferimento');
            $table->string('TipoCarburante', 50)->after('KmTotali');
            $table->date('DataUltimaAutorizzazioneSanitaria')->nullable()->after('TipoCarburante');
            $table->date('DataUltimoCollaudo')->nullable()->after('DataUltimaAutorizzazioneSanitaria');
        });
    }

    public function down(): void
    {
        Schema::table('automezzi', function (Blueprint $table) {
            $table->dropColumn([
                'Targa',
                'CodiceIdentificativo',
                'AnnoPrimaImmatricolazione',
                'Modello',
                'TipoVeicolo',
                'KmRiferimento',
                'KmTotali',
                'TipoCarburante',
                'DataUltimaAutorizzazioneSanitaria',
                'DataUltimoCollaudo',
            ]);
        });
    }
}

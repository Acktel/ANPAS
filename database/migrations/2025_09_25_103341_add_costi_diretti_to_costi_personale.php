<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('costi_personale', function (Blueprint $table) {
            // Base comuni
            if (!Schema::hasColumn('costi_personale', 'costo_diretto_Retribuzioni')) {
                $table->decimal('costo_diretto_Retribuzioni', 12, 2)->default(0)->after('Retribuzioni');
            }
            if (!Schema::hasColumn('costi_personale', 'costo_diretto_TFR')) {
                $table->decimal('costo_diretto_TFR', 12, 2)->default(0)->after('TFR');
            }
            if (!Schema::hasColumn('costi_personale', 'costo_diretto_Consulenze')) {
                $table->decimal('costo_diretto_Consulenze', 12, 2)->default(0)->after('Consulenze');
            }

            // Gestione oneri sociali: schema nuovo (INPS/INAIL) o vecchio (colonna unica)
            if (Schema::hasColumn('costi_personale', 'OneriSocialiInps')) {
                if (!Schema::hasColumn('costi_personale', 'costo_diretto_OneriSocialiInps')) {
                    $table->decimal('costo_diretto_OneriSocialiInps', 12, 2)->default(0)->after('OneriSocialiInps');
                }
            }

            if (Schema::hasColumn('costi_personale', 'OneriSocialiInail')) {
                if (!Schema::hasColumn('costi_personale', 'costo_diretto_OneriSocialiInail')) {
                    $table->decimal('costo_diretto_OneriSocialiInail', 12, 2)->default(0)->after('OneriSocialiInail');
                }
            }

            if (Schema::hasColumn('costi_personale', 'OneriSociali') && !Schema::hasColumn('costi_personale', 'costo_diretto_OneriSociali')) {
                $table->decimal('costo_diretto_OneriSociali', 12, 2)->default(0)->after('OneriSociali');
            }
        });
    }

    public function down(): void
    {
        Schema::table('costi_personale', function (Blueprint $table) {
            // Drop sicuri se esistono
            foreach ([
                'costo_diretto_Retribuzioni',
                'costo_diretto_TFR',
                'costo_diretto_Consulenze',
                'costo_diretto_OneriSocialiInps',
                'costo_diretto_OneriSocialiInail',
                'costo_diretto_OneriSociali',
            ] as $col) {
                if (Schema::hasColumn('costi_personale', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

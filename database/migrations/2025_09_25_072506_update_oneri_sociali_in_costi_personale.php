<?php

// database/migrations/2025_09_25_000001_update_oneri_sociali_in_costi_personale.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('costi_personale', function (Blueprint $table) {
            // rimuovo la vecchia colonna
            $table->dropColumn('OneriSociali');
        });

        Schema::table('costi_personale', function (Blueprint $table) {
            // aggiungo le due nuove colonne
            $table->decimal('OneriSocialiInps', 12, 2)->default(0)->after('Retribuzioni');
            $table->decimal('OneriSocialiInail', 12, 2)->default(0)->after('OneriSocialiInps');
        });
    }

    public function down(): void {
        Schema::table('costi_personale', function (Blueprint $table) {
            // rimuovo le due nuove colonne
            $table->dropColumn(['OneriSocialiInps', 'OneriSocialiInail']);
        });

        Schema::table('costi_personale', function (Blueprint $table) {
            // ripristino la vecchia colonna
            $table->decimal('OneriSociali', 12, 2)->default(0)->after('Retribuzioni');
        });
    }
};

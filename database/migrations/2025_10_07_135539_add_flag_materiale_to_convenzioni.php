<?php
// database/migrations/2025_01_01_000000_add_flag_materiale_to_convenzioni.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('convenzioni', function (Blueprint $table) {
            $table->boolean('materiale_fornito_asl')->default(false)->after('note');
        });

        // Backfill: se esisteva un collegamento in convenzioni_materiale_sanitario → imposta true
        if (Schema::hasTable('convenzioni_materiale_sanitario')) {
            DB::statement("
                UPDATE convenzioni c
                JOIN (
                  SELECT DISTINCT idConvenzione FROM convenzioni_materiale_sanitario
                ) cms ON cms.idConvenzione = c.idConvenzione
                SET c.materiale_fornito_asl = 1
            ");
        }
    }

    public function down(): void {
        Schema::table('convenzioni', function (Blueprint $table) {
            $table->dropColumn('materiale_fornito_asl');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Aggiungi colonna se manca (nullable per backfill)
        if (!Schema::hasColumn('aziende_sanitarie', 'idAnno')) {
            Schema::table('aziende_sanitarie', function (Blueprint $table) {
                $table->unsignedBigInteger('idAnno')->nullable()->after('idAziendaSanitaria');
            });
        }

        // 2) Backfill da convenzioni: MAX(c.idAnno) per ogni azienda
        DB::statement("
            UPDATE aziende_sanitarie AS a
            JOIN (
                SELECT asc2.idAziendaSanitaria, MAX(c.idAnno) AS idAnno
                FROM azienda_sanitaria_convenzione AS asc2
                JOIN convenzioni AS c ON c.idConvenzione = asc2.idConvenzione
                GROUP BY asc2.idAziendaSanitaria
            ) AS m ON m.idAziendaSanitaria = a.idAziendaSanitaria
            SET a.idAnno = m.idAnno
            WHERE a.idAnno IS NULL
        ");

        // 3) Backfill restante con anno corrente (crealo in 'anni' se non esiste)
        $annoCorrente = (int) now()->year;
        $idAnnoCorrente = DB::table('anni')->where('anno', $annoCorrente)->value('idAnno');
        if (!$idAnnoCorrente) {
            $idAnnoCorrente = DB::table('anni')->insertGetId([
                'anno'       => $annoCorrente,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('aziende_sanitarie')->whereNull('idAnno')->update(['idAnno' => $idAnnoCorrente]);

        // 4) Rendi NOT NULL se attualmente è nullable
        $nullable = DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'aziende_sanitarie')
            ->where('column_name', 'idAnno')
            ->value('IS_NULLABLE');

        if ($nullable === 'YES') {
            DB::statement('ALTER TABLE aziende_sanitarie MODIFY idAnno BIGINT UNSIGNED NOT NULL');
        }

        // 5) Aggiungi la FK solo se non esiste già
        $fkExists = DB::table('information_schema.table_constraints as tc')
            ->join('information_schema.key_column_usage as kcu', function($j){
                $j->on('tc.constraint_name', '=', 'kcu.constraint_name')
                  ->on('tc.table_schema', '=', 'kcu.table_schema')
                  ->on('tc.table_name', '=', 'kcu.table_name');
            })
            ->where('tc.table_schema', DB::getDatabaseName())
            ->where('tc.table_name', 'aziende_sanitarie')
            ->where('tc.constraint_type', 'FOREIGN KEY')
            ->where('kcu.column_name', 'idAnno')
            ->exists();

        if (!$fkExists) {
            // uso un nome esplicito e stabile per la FK
            DB::statement("
                ALTER TABLE aziende_sanitarie
                ADD CONSTRAINT fk_aziende_sanitarie_idAnno
                FOREIGN KEY (idAnno) REFERENCES anni(idAnno)
                ON DELETE CASCADE
            ");
        }
    }

    public function down(): void
    {
        // Droppa FK se esiste (qualunque nome)
        $constraints = DB::table('information_schema.table_constraints as tc')
            ->join('information_schema.key_column_usage as kcu', function($j){
                $j->on('tc.constraint_name', '=', 'kcu.constraint_name')
                  ->on('tc.table_schema', '=', 'kcu.table_schema')
                  ->on('tc.table_name', '=', 'kcu.table_name');
            })
            ->where('tc.table_schema', DB::getDatabaseName())
            ->where('tc.table_name', 'aziende_sanitarie')
            ->where('tc.constraint_type', 'FOREIGN KEY')
            ->where('kcu.column_name', 'idAnno')
            ->pluck('tc.constraint_name');

        foreach ($constraints as $name) {
            DB::statement("ALTER TABLE aziende_sanitarie DROP FOREIGN KEY `$name`");
        }

        if (Schema::hasColumn('aziende_sanitarie', 'idAnno')) {
            Schema::table('aziende_sanitarie', function (Blueprint $table) {
                $table->dropColumn('idAnno');
            });
        }
    }
};

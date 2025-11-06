<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class CostiMansioni
{
    protected const TABLE = 'costi_personale_mansioni';

    // [idQualifica => percentuale]
    public static function getPercentuali(int $idDipendente, int $anno): array
    {
        return DB::table(self::TABLE)
            ->where('idDipendente', $idDipendente)
            ->where('idAnno', $anno)
            ->pluck('percentuale', 'idQualifica')
            ->map(fn($v) => (float)$v)
            ->toArray();
    }

    // [idDipendente => percentuale] per una singola qualifica
    public static function getPercentualiByQualifica(int $idQualifica, int $anno): array
    {
        return DB::table(self::TABLE)
            ->where('idQualifica', $idQualifica)
            ->where('idAnno', $anno)
            ->pluck('percentuale', 'idDipendente')
            ->map(fn($v) => (float)$v)
            ->toArray();
    }

    public static function savePercentuali(int $idDipendente, int $anno, array $percentuali): void
    {
        DB::table(self::TABLE)
            ->where('idDipendente', $idDipendente)
            ->where('idAnno', $anno)
            ->delete();

        $now = now();
        $rows = [];
        foreach ($percentuali as $idQualifica => $pct) {
            $pct = (float)$pct;
            if ($pct <= 0) continue;
            $rows[] = [
                'idDipendente' => $idDipendente,
                'idAnno'       => $anno,
                'idQualifica'  => (int)$idQualifica,
                'percentuale'  => $pct,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        if ($rows) {
            DB::table(self::TABLE)->insert($rows);
        }
    }

    public static function deleteAllFor(int $idDipendente, int $anno): void
    {
        DB::table(self::TABLE)
            ->where('idDipendente', $idDipendente)
            ->where('idAnno', $anno)
            ->delete();
    }

    /**
     * Calcola il coefficiente (quota di costo) di una mansione per un dipendente.
     * Restituisce 1.0 se ha una sola mansione, altrimenti percentuale/100.
     */
    public static function coeffFor(int $anno, int $idDipendente, int $idQualifica): float
    {
        $n = DB::table('dipendenti_qualifiche')
            ->where('idDipendente', $idDipendente)
            ->count();

        if ($n <= 0) return 0.0;

        if ($n === 1) {
            return DB::table('dipendenti_qualifiche')
                ->where('idDipendente', $idDipendente)
                ->where('idQualifica', $idQualifica)
                ->exists() ? 1.0 : 0.0;
        }

        $pct = DB::table(self::TABLE)
            ->where('idAnno', $anno)
            ->where('idDipendente', $idDipendente)
            ->where('idQualifica', $idQualifica)
            ->value('percentuale');

        return max(0.0, ((float)$pct) / 100.0);
    }
}

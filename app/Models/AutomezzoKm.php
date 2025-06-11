<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutomezzoKm
{
    /**
     * Inserisce una riga in `automezzi_km`.
     */
    public static function add(
        int $idAutomezzo,
        int $idConvenzione,
        float $KMPercorsi
    ): int {
        return DB::table('automezzi_km')->insertGetId([
            'idAutomezzo'   => $idAutomezzo,
            'idConvenzione' => $idConvenzione,
            'KMPercorsi'    => $KMPercorsi,
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ], 'idAutomezzoKM');
    }

    /**
     * Recupera tutte le righe di km per un dato automezzo.
     */
    public static function getByAutomezzo(int $idAutomezzo): Collection
    {
        return DB::table('automezzi_km as k')
            ->join('convenzioni as c', 'k.idConvenzione', '=', 'c.idConvenzione')
            ->select([
                'k.idAutomezzoKM',
                'c.Convenzione',
                'k.KMPercorsi',
                'k.created_at',
            ])
            ->where('k.idAutomezzo', $idAutomezzo)
            ->orderBy('k.created_at', 'desc')
            ->get();
    }

    /**
     * Elimina tutte le righe di km collegate a un automezzo.
     */
    public static function deleteByAutomezzo(int $idAutomezzo): void
    {
        DB::table('automezzi_km')
            ->where('idAutomezzo', $idAutomezzo)
            ->delete();
    }

    /**
     * Elimina una singola riga di km (se serve).
     */
    public static function deleteSingle(int $idAutomezzoKM): void
    {
        DB::table('automezzi_km')
            ->where('idAutomezzoKM', $idAutomezzoKM)
            ->delete();
    }
}

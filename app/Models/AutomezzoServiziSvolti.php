<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\User;

class AutomezzoServiziSvolti {
    protected const TABLE = 'automezzi_servizi';

    /**
     * Inserisce un nuovo record.
     */
    public static function add(int $idAutomezzo, int $idConvenzione, int $numeroServizi): int {
        return DB::table(self::TABLE)->insertGetId([
            'idAutomezzo'      => $idAutomezzo,
            'idConvenzione'    => $idConvenzione,
            'NumeroServizi'    => $numeroServizi,
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now(),
        ], 'idAutomezzoServizi');
    }

    /**
     * Inserisce o aggiorna il numero servizi.
     */
    public static function upsert(int $idAutomezzo, int $idConvenzione, int $numeroServizi): void {
        DB::table(self::TABLE)->updateOrInsert(
            [
                'idAutomezzo'   => $idAutomezzo,
                'idConvenzione' => $idConvenzione,
            ],
            [
                'NumeroServizi' => $numeroServizi,
                'updated_at'    => Carbon::now(),
            ]
        );
    }

    /**
     * Elimina tutti i record di servizi per un automezzo.
     */
    public static function deleteByAutomezzo(int $idAutomezzo): void {
        DB::table(self::TABLE)
            ->where('idAutomezzo', $idAutomezzo)
            ->delete();
    }

    /**
     * Recupera i servizi raggruppati per automezzo e convenzione.
     */
    public static function getGroupedByAutomezzoAndConvenzione(int $anno, ?int $idAssociazione = null): Collection {

        $query = DB::table('automezzi_servizi as s')
            ->join('automezzi as a', 's.idAutomezzo', '=', 'a.idAutomezzo')
            ->where('a.idAnno', $anno);

        if (!is_null($idAssociazione)) {
            $query->where('a.idAssociazione', $idAssociazione);
        }

        return $query->select(
            's.idAutomezzo',
            's.idConvenzione',
            DB::raw('SUM(s.NumeroServizi) as NumeroServizi')
        )
            ->groupBy('s.idAutomezzo', 's.idConvenzione')
            ->get()
            ->groupBy(fn($row) => $row->idAutomezzo . '-' . $row->idConvenzione);
    }

    /**
     * Totale servizi svolti dall'associazione in un dato anno
     * (somma di NumeroServizi per tutti gli automezzi dell'associazione/anno).
     */
    public static function getTotaleByAssociazioneAnno(int $idAssociazione, int $anno): int {
        $tot = DB::table(self::TABLE . ' as s')
            ->join('automezzi as a', 's.idAutomezzo', '=', 'a.idAutomezzo')
            ->where('a.idAnno', $anno)
            ->where('a.idAssociazione', $idAssociazione)
            ->sum('s.NumeroServizi');

        return (int) ($tot ?? 0);
    }
}

<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Documento
{
    /**
     * Recupera i dati per il registro Excel (descrizione, preventivo, consuntivo).
     *
     * @param int $idAssociazione
     * @param int $idAnno
     * @return Collection
     */
    public static function getRegistroData(int $idAssociazione, int $idAnno): Collection
    {
        $query = DB::table('riepiloghi as r')
            ->join('riepilogo_dati as d', 'r.idRiepilogo', '=', 'd.idRiepilogo')
            ->where('r.idAssociazione', $idAssociazione)
            ->where('r.idAnno', $idAnno)
            ->orderBy('d.id')
            ->select([
                'd.descrizione',
                'd.preventivo',
                'd.consuntivo',
            ])
            ->get();
        return $query;

    }

    /**
     * Recupera i dati per la tabella convenzioni: descrizione e lettera.
     *
     * @param  int  $idAssociazione
     * @param  int  $idAnno
     * @return \Illuminate\Support\Collection
     */
    public static function getConvenzioniData(int $idAssociazione, int $idAnno)
    {
        return DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $idAnno)
            ->orderBy('lettera_identificativa')      // ordina per lettera
            ->select([
                'Convenzione',
                'lettera_identificativa',
            ])
            ->get();
    }
    /*
    // Se serviranno anche metodi per "distinta" e "criteri", puoi aggiungerli qui:
    public static function getDistintaData(int $idAssociazione, int $idAnno): Collection
    {
        // query analoga... 
    }

    public static function getCriteriData(int $idAssociazione, int $idAnno): Collection
    {
        // query analoga... 
    }
    */
}

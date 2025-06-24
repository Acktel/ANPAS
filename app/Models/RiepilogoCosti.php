<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RiepilogoCosti
{
    protected static array $views = [
        'personale' => 'view_riepilogo_costi_personale',
        'struttura' => 'view_riepilogo_gestione_struttura',
        'automezzi' => 'view_riepilogo_automezzi',
    ];

    /**
     * Restituisce le voci di una tipologia per anno e associazione
     */
    public static function getByTipologia(int $idTipologia, int $anno, ?int $idAssociazione = null)
    {
        $query = DB::table('riepilogo_dati as rd')
            ->join('riepiloghi as r', 'rd.idRiepilogo', '=', 'r.idRiepilogo')
            ->where('r.idAnno', $anno)
            ->where('rd.idTipologiaRiepilogo', $idTipologia);

        if (!is_null($idAssociazione)) {
            $query->where('r.idAssociazione', $idAssociazione);
        }

        return $query->select([
                'rd.id',
                'rd.descrizione',
                'rd.preventivo',
                'rd.consuntivo',
                'rd.idTipologiaRiepilogo',
            ])
            ->get()
->map(function ($item) {
    $item->scostamento = $item->preventivo != 0
        ? number_format((($item->consuntivo - $item->preventivo) / $item->preventivo) * 100, 2) . '%'
        : '0%';

    $item->actions = view('partials.actions_inline', [
        'id' => $item->id
    ])->render();

    return $item;
});

    }

    /**
     * Restituisce l'id di riepilogo per anno e associazione, creandolo se non esiste
     */
    public static function getOrCreateRiepilogo(int $idAssociazione, int $anno): int
    {
        $record = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if ($record) {
            return $record->idRiepilogo;
        }

        return DB::table('riepiloghi')->insertGetId([
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $anno,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    /**
     * Inserisce una nuova voce nel riepilogo_dati
     */
    public static function createVoce(array $data): bool
    {
        return DB::table('riepilogo_dati')->insert([
            'idRiepilogo'           => $data['idRiepilogo'],
            'idAnno'                => $data['idAnno'],
            'idTipologiaRiepilogo' => $data['idTipologiaRiepilogo'],
            'descrizione'          => $data['descrizione'],
            'preventivo'           => $data['preventivo'],
            'consuntivo'           => $data['consuntivo'],
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }
}

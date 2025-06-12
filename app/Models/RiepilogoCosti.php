<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RiepilogoCosti
{
    protected static array $views = [
        'personale'  => 'view_riepilogo_costi_personale',
        'struttura'  => 'view_riepilogo_gestione_struttura',
        'automezzi'  => 'view_riepilogo_automezzi',
    ];

    /**
     * Restituisce i dati da una view in base al tipo (per sezioni legacy)
     */
    public static function getSezione(string $tipo, int $anno, int $idAssociazione)
    {
        $view = self::$views[$tipo] ?? null;

        if (! $view) return collect();

        return DB::table($view)
            ->where('anno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->get();
    }

    /**
     * Restituisce le voci di una tipologia per anno e associazione
     */
    public static function getByTipologia(int $idTipologia, int $anno, int $idAssociazione)
    {
        return DB::table('riepilogo_dati as rd')
            ->join('riepiloghi as r', 'rd.idRiepilogo', '=', 'r.idRiepilogo')
            ->where('r.idAnno', $anno)
            ->where('r.idAssociazione', $idAssociazione)
            ->where('rd.idTipologiaRiepilogo', $idTipologia)
            ->select('rd.id', 'rd.descrizione', 'rd.preventivo', 'rd.consuntivo')
            ->get()
            ->map(function ($item) use ($idTipologia) {
                $item->scostamento = $item->preventivo != 0
                    ? number_format((($item->consuntivo - $item->preventivo) / $item->preventivo) * 100, 2) . '%'
                    : '0%';

                $item->actions = view('partials.actions', [
                    'id'              => $item->id,
                    'idTipologia'     => $idTipologia,
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

        if ($record) return $record->idRiepilogo;

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

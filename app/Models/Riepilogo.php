<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class Riepilogo extends Model
{
    // Configurazione del modello per il route model binding
    protected $table = 'riepiloghi';
    protected $primaryKey = 'idRiepilogo';
    public $incrementing = true;
    protected $keyType = 'int';

    // Disabilita i timestamp di Eloquent (usiamo Carbon manualmente nelle statiche)
    public $timestamps = false;

    /**
     * Crea un nuovo record in tabella `riepiloghi` e ritorna l'id inserito.
     *
     * @param int $idAssociazione
     * @param int $idAnno
     * @return int
     * @throws \Exception
     */
    public static function createRiepilogo(int $idAssociazione, int $idAnno): int
    {
        return DB::table('riepiloghi')->insertGetId([
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $idAnno,
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ], 'idRiepilogo');
    }

    /**
     * Inserisce una riga in `riepilogo_dati`.
     *
     * @param int    $idRiepilogo
     * @param string $descrizione
     * @param float  $preventivo
     * @param float  $consuntivo
     * @return void
     * @throws \Exception
     */
    public static function addDato(
        int $idRiepilogo,
        string $descrizione,
        float $preventivo,
        float $consuntivo
    ): void {
        DB::table('riepilogo_dati')->insert([
            'idRiepilogo' => $idRiepilogo,
            'descrizione' => $descrizione,
            'preventivo'  => $preventivo,
            'consuntivo'  => $consuntivo,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);
    }

    /**
     * Recupera tutti i riepiloghi (per utente Admin/SuperAdmin/Supervisor).
     *
     * @return Collection
     */
    public static function getAllForAdmin(): Collection
    {
        return DB::table('riepiloghi as r')
            ->join('associazioni as s', 'r.idAssociazione', '=', 's.idAssociazione')
            ->select(
                'r.idRiepilogo',
                's.Associazione',
                'r.idAnno as anno',
                'r.created_at'
            )
            ->orderBy('s.Associazione')
            ->orderBy('r.idAnno', 'desc')
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    /**
     * Recupera l'elenco dei riepiloghi per una data associazione.
     *
     * @param int $idAssociazione
     * @return Collection
     */
    public static function getByAssociazione(int $idAssociazione): Collection
    {
        return DB::table('riepiloghi as r')
            ->where('r.idAssociazione', $idAssociazione)
            ->select('r.idRiepilogo', 'r.idAnno as anno', 'r.created_at')
            ->orderBy('r.idAnno', 'desc')
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    /**
     * Recupera il singolo riepilogo.
     *
     * @param int $idRiepilogo
     * @return object|null
     */
    public static function getSingle(int $idRiepilogo)
    {
        return DB::table('riepiloghi')
            ->where('idRiepilogo', $idRiepilogo)
            ->first();
    }

    /**
     * Recupera i dati di dettaglio per un singolo riepilogo.
     *
     * @param int $idRiepilogo
     * @return Collection
     */
    public static function getDati(int $idRiepilogo): Collection
    {
        return DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Aggiorna il record principale di `riepiloghi`.
     *
     * @param int $idRiepilogo
     * @param int $idAssociazione
     * @param int $idAnno
     * @return void
     */
    public static function updateRiepilogo(int $idRiepilogo, int $idAssociazione, int $idAnno): void
    {
        DB::table('riepiloghi')
            ->where('idRiepilogo', $idRiepilogo)
            ->update([
                'idAssociazione' => $idAssociazione,
                'idAnno'         => $idAnno,
                'updated_at'     => Carbon::now(),
            ]);
    }

    /**
     * Elimina tutte le righe di `riepilogo_dati` collegate.
     *
     * @param int $idRiepilogo
     * @return void
     */
    public static function deleteDati(int $idRiepilogo): void
    {
        DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->delete();
    }

    /**
     * Elimina il record principale di `riepiloghi`.
     *
     * @param int $idRiepilogo
     * @return void
     */
    public static function deleteRiepilogo(int $idRiepilogo): void
    {
        DB::table('riepiloghi')
            ->where('idRiepilogo', $idRiepilogo)
            ->delete();
    }
    
}

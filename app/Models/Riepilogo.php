<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class Riepilogo extends Model
{
    protected $table = 'riepiloghi';
    protected $primaryKey = 'idRiepilogo';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    public static function createRiepilogo(int $idAssociazione, int $idAnno): int
    {
        return DB::table('riepiloghi')->insertGetId([
            'idAssociazione' => $idAssociazione,
            'idAnno'         => $idAnno,
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ], 'idRiepilogo');
    }

    public static function addDato(int $idRiepilogo, string $descrizione, float $preventivo, float $consuntivo): void
    {
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
     * 🔁 Riepiloghi per admin filtrati per anno in sessione
     */
    public static function getAllForAdmin(?int $anno = null): Collection
    {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table('riepiloghi as r')
            ->join('associazioni as s', 'r.idAssociazione', '=', 's.idAssociazione')
            ->select(
                'r.idRiepilogo',
                's.Associazione',
                'r.idAnno as anno',
                'r.created_at'
            )
            ->where('r.idAnno', $anno)
            ->orderBy('s.Associazione')
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    /**
     * 🔒 Riepiloghi per associazione e anno dinamico
     */
    public static function getByAssociazione(int $idAssociazione, ?int $anno = null): Collection
    {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table('riepiloghi as r')
            ->where('r.idAssociazione', $idAssociazione)
            ->where('r.idAnno', $anno)
            ->select('r.idRiepilogo', 'r.idAnno as anno', 'r.created_at')
            ->orderBy('r.created_at', 'desc')
            ->get();
    }

    public static function getSingle(int $idRiepilogo)
    {
        return DB::table('riepiloghi')
            ->where('idRiepilogo', $idRiepilogo)
            ->first();
    }

    public static function getDati(int $idRiepilogo): Collection
    {
        return DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->orderBy('id', 'asc')
            ->get();
    }

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

    public static function deleteDati(int $idRiepilogo): void
    {
        DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->delete();
    }

    public static function deleteRiepilogo(int $idRiepilogo): void
    {
        DB::table('riepiloghi')
            ->where('idRiepilogo', $idRiepilogo)
            ->delete();
    }
}

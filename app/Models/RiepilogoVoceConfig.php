<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class RiepilogoVoceConfig
{
    protected const TABLE = 'riepilogo_voci_config';

    public static function listTipologie()
    {
        // usa la tua tabella tipologia_riepilogo
        return DB::table('tipologia_riepilogo')->select('id','descrizione')->orderBy('id')->get();
    }

    public static function allByTipologia(): array
    {
        $rows = DB::table(self::TABLE)
            ->orderBy('idTipologiaRiepilogo')
            ->orderBy('ordinamento')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->idTipologiaRiepilogo][] = $r;
        }
        return $out;
    }

    public static function createVoce(array $d): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'idTipologiaRiepilogo' => (int)$d['idTipologiaRiepilogo'],
            'descrizione'          => $d['descrizione'],
            'ordinamento'          => (int)($d['ordinamento'] ?? 0),
            'attivo'               => (bool)($d['attivo'] ?? true),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    public static function updateVoce(int $id, array $d): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->update([
            'idTipologiaRiepilogo' => (int)$d['idTipologiaRiepilogo'],
            'descrizione'          => $d['descrizione'],
            'ordinamento'          => (int)($d['ordinamento'] ?? 0),
            'attivo'               => (bool)($d['attivo'] ?? true),
            'updated_at'           => now(),
        ]) > 0;
    }

    public static function deleteVoce(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public static function reorder(array $ids): void
    {
        foreach ($ids as $i => $id) {
            DB::table(self::TABLE)->where('id', $id)->update(['ordinamento' => $i]);
        }
    }
}

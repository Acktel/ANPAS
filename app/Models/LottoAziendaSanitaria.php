<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class LottoAziendaSanitaria
{
    protected static string $table = 'aziende_sanitarie_lotti';

    public static function getAllWithAziende(): \Illuminate\Support\Collection
    {
        return DB::table(self::$table . ' as l')
            ->join('aziende_sanitarie as a', 'l.idAziendaSanitaria', '=', 'a.idAziendaSanitaria')
            ->select('l.id', 'l.nomeLotto', 'a.Nome as nomeAzienda')
            ->orderBy('a.Nome')
            ->orderBy('l.nomeLotto')
            ->get();
    }

    public static function createLotto(array $data): int
    {
        return DB::table(self::$table)->insertGetId([
            'idAziendaSanitaria' => $data['idAziendaSanitaria'],
            'nomeLotto' => $data['nomeLotto'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function deleteLotto(int $id): void
    {
        DB::table(self::$table)
            ->where('id', $id)
            ->delete();
    }
}

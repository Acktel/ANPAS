<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class LottoAziendaSanitaria
{
    protected static string $table = 'aziende_sanitarie_lotti';

    public static function allWithAziende(?int $idAziendaSanitaria = null): Collection
    {
        $q = DB::table(self::$table.' as l')
            ->join('aziende_sanitarie as a', 'l.idAziendaSanitaria', '=', 'a.idAziendaSanitaria')
            ->select('l.id', 'l.nomeLotto', 'l.idAziendaSanitaria', 'a.Nome as nomeAzienda')
            ->orderBy('a.Nome')
            ->orderBy('l.nomeLotto');

        if ($idAziendaSanitaria) {
            $q->where('l.idAziendaSanitaria', $idAziendaSanitaria);
        }

        return $q->get();
    }

    public static function create(array $data): int
    {
        return DB::table(self::$table)->insertGetId([
            'idAziendaSanitaria' => $data['idAziendaSanitaria'],
            'nomeLotto' => $data['nomeLotto'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function deleteById(int $id): int
    {
        return DB::table(self::$table)->where('id', $id)->delete();
    }
}

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
            ->select('l.id', 'l.nomeLotto', 'l.descrizione', 'l.idAziendaSanitaria', 'a.Nome as nomeAzienda')
            ->orderBy('a.Nome')
            ->orderBy('l.nomeLotto');

        if ($idAziendaSanitaria) {
            $q->where('l.idAziendaSanitaria', $idAziendaSanitaria);
        }

        return $q->get();
    }

    public static function getByAzienda(int $idAziendaSanitaria): Collection
    {
        return DB::table(self::$table)
            ->where('idAziendaSanitaria', $idAziendaSanitaria)
            ->orderBy('nomeLotto')
            ->get();
    }

    public static function create(array $data): int
    {
        return DB::table(self::$table)->insertGetId([
            'idAziendaSanitaria' => $data['idAziendaSanitaria'],
            'nomeLotto'          => $data['nomeLotto'],
            'descrizione'        => $data['descrizione'] ?? null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public static function updateById(int $id, array $data): int
    {
        return DB::table(self::$table)->where('id', $id)->update([
            'nomeLotto'   => $data['nomeLotto'],
            'descrizione' => $data['descrizione'] ?? null,
            'updated_at'  => now(),
        ]);
    }

    public static function deleteById(int $id): int
    {
        return DB::table(self::$table)->where('id', $id)->delete();
    }

    public static function deleteByAziendaExceptIds(int $idAziendaSanitaria, array $keepIds): int
    {
        return DB::table(self::$table)
            ->where('idAziendaSanitaria', $idAziendaSanitaria)
            ->when(!empty($keepIds), fn($q)=>$q->whereNotIn('id', $keepIds))
            ->delete();
    }

    /**
     * Sincronizza i lotti di un’azienda:
     * $payload = [
     *   ['id'=>?, 'nomeLotto'=>'..', 'descrizione'=>'..'], // update
     *   ['nomeLotto'=>'..', 'descrizione'=>'..'],          // insert
     *   ... (quelli non presenti verranno eliminati)
     * ]
     */
    public static function syncForAzienda(int $idAziendaSanitaria, array $payload): void
    {
        $now = now();
        $keepIds = [];

        foreach ($payload as $row) {
            $nome   = trim((string)($row['nomeLotto'] ?? ''));
            $delete = (bool)($row['_delete'] ?? false);
            if ($delete || $nome === '') continue;

            $data = [
                'nomeLotto'   => $nome,
                'descrizione' => $row['descrizione'] ?? null,
            ];

            if (!empty($row['id'])) {
                self::updateById((int)$row['id'], $data);
                $keepIds[] = (int)$row['id'];
            } else {
                $id = DB::table(self::$table)->insertGetId([
                    'idAziendaSanitaria' => $idAziendaSanitaria,
                    'nomeLotto'          => $data['nomeLotto'],
                    'descrizione'        => $data['descrizione'],
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
                $keepIds[] = $id;
            }
        }

        // elimina ciò che non è nel payload
        self::deleteByAziendaExceptIds($idAziendaSanitaria, $keepIds);
    }
}

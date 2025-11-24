<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class LottoAziendaSanitaria {
    protected static string $table = 'aziende_sanitarie_lotti';

    public static function allWithAziende(?int $idAziendaSanitaria = null): Collection {
        $q = DB::table(self::$table . ' as l')
            ->join('aziende_sanitarie as a', 'l.idAziendaSanitaria', '=', 'a.idAziendaSanitaria')
            ->select('l.id', 'l.nomeLotto', 'l.descrizione', 'l.idAziendaSanitaria', 'a.Nome as nomeAzienda')
            ->orderBy('a.Nome')
            ->orderBy('l.nomeLotto');

        if ($idAziendaSanitaria) {
            $q->where('l.idAziendaSanitaria', $idAziendaSanitaria);
        }

        return $q->get();
    }

    public static function getByAzienda(int $idAziendaSanitaria): Collection {
        return DB::table(self::$table)
            ->where('idAziendaSanitaria', $idAziendaSanitaria)
            ->orderBy('nomeLotto')
            ->get();
    }

    public static function create(array $data): int {
        return DB::table(self::$table)->insertGetId([
            'idAziendaSanitaria' => $data['idAziendaSanitaria'],
            'nomeLotto'          => $data['nomeLotto'],
            'descrizione'        => $data['descrizione'] ?? null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public static function updateById(int $id, array $data): int {
        return DB::table(self::$table)->where('id', $id)->update([
            'nomeLotto'   => $data['nomeLotto'],
            'descrizione' => $data['descrizione'] ?? null,
            'updated_at'  => now(),
        ]);
    }

    public static function deleteById(int $id): int {
        return DB::table(self::$table)->where('id', $id)->delete();
    }

    public static function deleteByAziendaExceptIds(int $idAziendaSanitaria, array $keepIds): int {
        return DB::table(self::$table)
            ->where('idAziendaSanitaria', $idAziendaSanitaria)
            ->when(!empty($keepIds), fn($q) => $q->whereNotIn('id', $keepIds))
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
    public static function syncForAzienda(int $idAziendaSanitaria, array $payload): void {
        $now = now();

        foreach ($payload as $row) {

            $id       = isset($row['id']) ? (int)$row['id'] : null;
            $nome     = trim((string)($row['nomeLotto'] ?? ''));
            $desc     = $row['descrizione'] ?? null;
            $toDelete = (bool)($row['_delete'] ?? false);

            // 1) Se devo cancellare → cancello e basta
            if ($toDelete && $id) {
                DB::table(self::$table)->where('id', $id)->delete();
                continue;
            }

            // 2) Se non c’è nome lotto → ignoro (non creo nulla)
            if ($nome === '') {
                continue;
            }

            // 3) UPDATE
            if ($id) {
                DB::table(self::$table)
                    ->where('id', $id)
                    ->update([
                        'nomeLotto'   => $nome,
                        'descrizione' => $desc,
                        'updated_at'  => $now,
                    ]);
            }

            // 4) INSERT
            else {
                DB::table(self::$table)->insert([
                    'idAziendaSanitaria' => $idAziendaSanitaria,
                    'nomeLotto'          => $nome,
                    'descrizione'        => $desc,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
            }
        }

        // NOTA IMPORTANTE:
        // NON cancelliamo automaticamente tutti gli altri,
        // perché il form non spedisce tutte le righe, solo quelle visibili
        // (e quelle nascoste col _delete manuale)
    }
}

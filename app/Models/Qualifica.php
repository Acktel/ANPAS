<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Qualifica
{
    protected const TABLE = 'qualifiche';

    public static function getAll(): Collection {
        return DB::table(self::TABLE)
            ->select('id','nome','ordinamento','attivo')
            ->orderBy('ordinamento')
            ->orderBy('id')
            ->get();
    }

        public static function updateById(int $id, array $data): bool {
        $upd = [
            'ordinamento' => $data['ordinamento'] ?? 0,
            'attivo'      => (int)($data['attivo'] ?? 0),
            'updated_at'  => now(),
        ];
        return DB::table(self::TABLE)->where('id', $id)->update($upd) > 0;
    }

        public static function reorder(array $idsInOrder): void {
        // assegna ordinamento 0..n secondo l’ordine ricevuto
        DB::transaction(function () use ($idsInOrder) {
            foreach ($idsInOrder as $pos => $id) {
                DB::table(self::TABLE)->where('id', (int)$id)->update([
                    'ordinamento' => $pos,
                    'updated_at'  => now(),
                ]);
            }
        });
    }

    public static function getById(int $id)
    {
        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    public static function createQualifica(array $data) {
        abort(403, 'Le qualifiche hanno ID fissi: creazione non consentita.');
    }
    public static function deleteById(int $id): bool {
        abort(403, 'Le qualifiche hanno ID fissi: eliminazione non consentita.');
    }
}

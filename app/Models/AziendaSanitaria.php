<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class AziendaSanitaria {
    protected static string $table = 'aziende_sanitarie';

    public static function getAllWithConvenzioni(): \Illuminate\Support\Collection {
        return DB::table('aziende_sanitarie as a')
            ->leftJoin('azienda_sanitaria_convenzione as ac', 'a.idAziendaSanitaria', '=', 'ac.idAziendaSanitaria')
            ->leftJoin('convenzioni as c', 'ac.idConvenzione', '=', 'c.idConvenzione')
            ->leftJoin('aziende_sanitarie_lotti as l', 'a.idAziendaSanitaria', '=', 'l.idAziendaSanitaria')
            ->select(
                'a.idAziendaSanitaria',
                'a.Nome',
                'a.Indirizzo',
                'a.mail',
                DB::raw('GROUP_CONCAT(DISTINCT c.Convenzione ORDER BY c.Convenzione SEPARATOR ", ") as Convenzioni'),
                DB::raw('GROUP_CONCAT(DISTINCT l.nomeLotto ORDER BY l.nomeLotto SEPARATOR ", ") as Lotti')
            )
            ->groupBy(
                'a.idAziendaSanitaria',
                'a.Nome',
                'a.Indirizzo',
                'a.mail'
            )
            ->get()
            ->map(function ($a) {
                $a->Convenzioni = explode(', ', $a->Convenzioni ?? '');
                $a->Lotti = explode(', ', $a->Lotti ?? '');
                return $a;
            });
    }

    public static function getById(int $id): ?\stdClass {
        return DB::table(self::$table)
            ->where('idAziendaSanitaria', $id)
            ->first();
    }

    public static function createSanitaria(array $data): int {
        $id = DB::table(self::$table)->insertGetId([
            'Nome'       => $data['Nome'],
            'Indirizzo'  => $data['Indirizzo'] ?? null,
            'mail'       => $data['mail'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::syncConvenzioni($id, $data['convenzioni'] ?? []);

        return $id;
    }

    public static function updateSanitaria(int $id, array $data): void {
        DB::table(self::$table)
            ->where('idAziendaSanitaria', $id)
            ->update([
                'Nome'       => $data['Nome'],
                'Indirizzo'  => $data['Indirizzo'] ?? null,
                'mail'       => $data['mail'] ?? null,
                'updated_at' => now(),
            ]);

        self::syncConvenzioni($id, $data['convenzioni'] ?? []);
    }

    public static function deleteSanitaria(int $id): void {
        DB::table('azienda_sanitaria_convenzione')->where('idAziendaSanitaria', $id)->delete();
        DB::table(self::$table)->where('idAziendaSanitaria', $id)->delete();
    }

    public static function getConvenzioni(int $id): array {
        return DB::table('azienda_sanitaria_convenzione')
            ->where('idAziendaSanitaria', $id)
            ->pluck('idConvenzione')
            ->toArray();
    }

    public static function syncConvenzioni(int $idAzienda, array $convenzioni): void {
        DB::table('azienda_sanitaria_convenzione')->where('idAziendaSanitaria', $idAzienda)->delete();

        if (!empty($convenzioni)) {
            $now = now();
            $data = array_map(fn($idConv) => [
                'idAziendaSanitaria' => $idAzienda,
                'idConvenzione'      => $idConv,
                'created_at'         => $now,
                'updated_at'         => $now,
            ], $convenzioni);

            DB::table('azienda_sanitaria_convenzione')->insert($data);
        }
    }

    public static function getAll() {
        return DB::table('aziende_sanitarie')
            ->select('idAziendaSanitaria', 'Nome')
            ->orderBy('Nome')
            ->get();
    }
}

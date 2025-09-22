<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AziendaSanitaria {
    protected static string $table = 'aziende_sanitarie';

public static function getAllWithConvenzioni($idConvenzione = null): \Illuminate\Support\Collection
{
    $query = DB::table('aziende_sanitarie as a')
        ->leftJoin('azienda_sanitaria_convenzione as ac', 'a.idAziendaSanitaria', '=', 'ac.idAziendaSanitaria')
        ->leftJoin('convenzioni as c', 'ac.idConvenzione', '=', 'c.idConvenzione')
        ->leftJoin('aziende_sanitarie_lotti as l', 'a.idAziendaSanitaria', '=', 'l.idAziendaSanitaria')
        ->select(
            'a.idAziendaSanitaria',
            'a.Nome',
            'a.Indirizzo',
            'a.provincia',
            'a.citta',
            'a.mail',
            DB::raw('GROUP_CONCAT(DISTINCT c.Convenzione ORDER BY c.Convenzione SEPARATOR ", ") as Convenzioni'),
            DB::raw('GROUP_CONCAT(DISTINCT l.nomeLotto ORDER BY l.nomeLotto SEPARATOR ", ") as Lotti')
        )
        ->groupBy(
            'a.idAziendaSanitaria',
            'a.Nome',
            'a.Indirizzo',
            'a.provincia',
            'a.citta',
            'a.mail'
        );

    // Se passato un filtro, includi solo le aziende che hanno quella/e convenzione/i
    if (!empty($idConvenzione)) {
        // normalizza in array (accetta singolo id o array)
        $ids = is_array($idConvenzione) ? $idConvenzione : [$idConvenzione];

        $query->whereExists(function ($q) use ($ids) {
            $q->select(DB::raw(1))
              ->from('azienda_sanitaria_convenzione as ac2')
              ->whereColumn('ac2.idAziendaSanitaria', 'a.idAziendaSanitaria')
              ->whereIn('ac2.idConvenzione', $ids);
        });
    }

    return $query->get()
        ->map(function ($a) {
            // se la stringa è vuota o null, ritorna array vuoto
            $a->Convenzioni = strlen(trim((string)($a->Convenzioni ?? ''))) ? explode(', ', $a->Convenzioni) : [];
            $a->Lotti = strlen(trim((string)($a->Lotti ?? ''))) ? explode(', ', $a->Lotti) : [];
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
            'provincia'  => $data['provincia'],
            'citta'      => $data['citta'],
            'mail'       => $data['mail'] ?? null,
            'note'       => $data['note'] ?? null,
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
                'provincia'  => $data['provincia'],
                'citta'      => $data['citta'],
                'mail'       => $data['mail'] ?? null,
                'note'       => $data['note'] ?? null,
                'updated_at' => now(),
            ]);

        self::syncConvenzioni($id, $data['convenzioni'] ?? []);
    }

    public static function deleteSanitaria(int $id): void {
        DB::table('azienda_sanitaria_convenzione')->where('idAziendaSanitaria', $id)->delete();
        DB::table('aziende_sanitarie_lotti')->where('idAziendaSanitaria', $id)->delete(); // <— aggiungi
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

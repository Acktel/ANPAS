<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AziendaSanitaria {
    protected static string $table = 'aziende_sanitarie';

    /**
     * Lista aziende + convenzioni + lotti (con filtro opzionale su idConvenzione)
     * SQL grezzo (MySQL: usa GROUP_CONCAT).
     */
    public static function getAllWithConvenzioni($idConvenzione = null): Collection {
        $anno = (int) session('anno_riferimento', now()->year);

        $sql = "
        SELECT
            a.idAziendaSanitaria,
            a.Nome,
            a.Indirizzo,
            a.mail,
            GROUP_CONCAT(DISTINCT c.Convenzione ORDER BY c.Convenzione SEPARATOR ', ') AS Convenzioni,
            GROUP_CONCAT(DISTINCT l.nomeLotto   ORDER BY l.nomeLotto   SEPARATOR ', ') AS Lotti
        FROM aziende_sanitarie a
        LEFT JOIN azienda_sanitaria_convenzione ac
            ON a.idAziendaSanitaria = ac.idAziendaSanitaria
        LEFT JOIN convenzioni c
            ON ac.idConvenzione = c.idConvenzione
           AND c.idAnno = ?                  -- filtro per anno in JOIN per preservare il LEFT JOIN
        LEFT JOIN aziende_sanitarie_lotti l
            ON a.idAziendaSanitaria = l.idAziendaSanitaria
        WHERE 1 = 1
    ";

        // primo binding: l'anno
        $bindings = [$anno];

        // Filtro opzionale su idConvenzione (singolo o array)
        if (!empty($idConvenzione)) {
            $ids = is_array($idConvenzione) ? array_values($idConvenzione) : [$idConvenzione];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $sql .= "
            AND EXISTS (
                SELECT 1
                FROM azienda_sanitaria_convenzione ac2
                WHERE ac2.idAziendaSanitaria = a.idAziendaSanitaria
                  AND ac2.idConvenzione IN ($placeholders)
            )
        ";

            foreach ($ids as $id) {
                $bindings[] = (int) $id;
            }
        }

        $sql .= "
        GROUP BY a.idAziendaSanitaria, a.Nome, a.Indirizzo, a.mail
        ORDER BY a.Nome
    ";

        $rows = DB::select($sql, $bindings);

        return collect($rows)->map(function ($a) {
            $a->Convenzioni = strlen(trim((string)($a->Convenzioni ?? '')))
                ? explode(', ', $a->Convenzioni) : [];
            $a->Lotti = strlen(trim((string)($a->Lotti ?? '')))
                ? explode(', ', $a->Lotti) : [];
            return $a;
        });
    }


    public static function getById(int $id): ?\stdClass {
        $sql = "SELECT * FROM " . self::$table . " WHERE idAziendaSanitaria = ? LIMIT 1";
        $row = DB::select($sql, [$id]);
        return $row[0] ?? null;
    }

    public static function createSanitaria(array $data): int {
        $sql = "
            INSERT INTO " . self::$table . "
                (Nome, Indirizzo, mail, note, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, NOW(), NOW())
        ";

        DB::insert($sql, [
            $data['Nome'],
            $data['Indirizzo'] ?? null,
            $data['mail'] ?? null,
            $data['note'] ?? null,
        ]);

        $id = (int) DB::getPdo()->lastInsertId();

        // pivot (se passato)
        self::syncConvenzioni($id, $data['convenzioni'] ?? []);

        return $id;
    }

    public static function updateSanitaria(int $id, array $data): void {
        $sql = "
            UPDATE " . self::$table . "
            SET Nome = ?, Indirizzo = ?, mail = ?, note = ?, updated_at = NOW()
            WHERE idAziendaSanitaria = ?
        ";

        DB::update($sql, [
            $data['Nome'],
            $data['Indirizzo'] ?? null,
            $data['mail'] ?? null,
            $data['note'] ?? null,
            $id,
        ]);

        // pivot (se passato)
        if (array_key_exists('convenzioni', $data)) {
            self::syncConvenzioni($id, $data['convenzioni'] ?? []);
        }
    }

    public static function deleteSanitaria(int $id): void {
        // pivot
        DB::delete("DELETE FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?", [$id]);
        // lotti
        DB::delete("DELETE FROM aziende_sanitarie_lotti WHERE idAziendaSanitaria = ?", [$id]);
        // azienda
        DB::delete("DELETE FROM " . self::$table . " WHERE idAziendaSanitaria = ?", [$id]);
    }

    public static function getConvenzioni(int $id): array {
        $rows = DB::select(
            "SELECT idConvenzione FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?",
            [$id]
        );
        return array_map(fn($r) => (int) $r->idConvenzione, $rows);
    }

    public static function syncConvenzioni(int $idAzienda, array $convenzioni): void {
        // wipe & reinsert
        DB::delete("DELETE FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?", [$idAzienda]);

        if (empty($convenzioni)) {
            return;
        }

        // build multi-insert
        $valuesClauses = [];
        $bindings = [];
        foreach ($convenzioni as $idConv) {
            $valuesClauses[] = "(?, ?, NOW(), NOW())";
            $bindings[] = $idAzienda;
            $bindings[] = (int) $idConv;
        }

        $sql = "
            INSERT INTO azienda_sanitaria_convenzione
                (idAziendaSanitaria, idConvenzione, created_at, updated_at)
            VALUES " . implode(", ", $valuesClauses);

        DB::insert($sql, $bindings);
    }

    public static function getAll(): Collection {
        $rows = DB::select("
            SELECT idAziendaSanitaria, Nome
            FROM aziende_sanitarie
            ORDER BY Nome
        ");
        return collect($rows);
    }
}

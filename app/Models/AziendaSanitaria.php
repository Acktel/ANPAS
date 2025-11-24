<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AziendaSanitaria {
    protected static string $table = 'aziende_sanitarie';

    /** Resolve idAnno from year, creates record if missing. */
    public static function resolveIdAnno(int $anno): int {
        $idAnno = DB::table('anni')->where('anno', $anno)->value('idAnno');
        if (!$idAnno) {
            $idAnno = DB::table('anni')->insertGetId([
                'anno'       => $anno,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return (int) $idAnno;
    }

    /** Lista aziende + convenzioni + lotti per anno corrente (con filtro opzionale su idConvenzione). */
    public static function getAllWithConvenzioni($idConvenzione = null): Collection {
        $anno   = (int) session('anno_riferimento', now()->year);
        $idAnno = self::resolveIdAnno($anno);

        $sql = "
        SELECT
            a.idAziendaSanitaria,
            a.Nome,
            a.Indirizzo,
            a.provincia,
            a.citta,
            a.cap,
            a.mail,
            cg.Convenzioni,
            lg.Lotti
        FROM aziende_sanitarie a
        LEFT JOIN (
            SELECT
                ac.idAziendaSanitaria,
                GROUP_CONCAT(DISTINCT c.Convenzione ORDER BY c.Convenzione SEPARATOR ', ') AS Convenzioni
            FROM azienda_sanitaria_convenzione ac
            JOIN convenzioni c
                ON c.idConvenzione = ac.idConvenzione
               AND c.idAnno = ?
            GROUP BY ac.idAziendaSanitaria
        ) cg ON cg.idAziendaSanitaria = a.idAziendaSanitaria
        LEFT JOIN (
            SELECT
                l.idAziendaSanitaria,
                GROUP_CONCAT(DISTINCT l.nomeLotto ORDER BY l.nomeLotto SEPARATOR ', ') AS Lotti
            FROM aziende_sanitarie_lotti l
            GROUP BY l.idAziendaSanitaria
        ) lg ON lg.idAziendaSanitaria = a.idAziendaSanitaria
        WHERE a.idAnno = ?
    ";

        $bindings = [$idAnno, $idAnno];

        // ===== FILTRO PER CONVENZIONE =====
        if (!empty($idConvenzione)) {
            $ids = is_array($idConvenzione) ? $idConvenzione : [$idConvenzione];
            $ids = array_map('intval', $ids);

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $sql .= "
            AND EXISTS (
                SELECT 1
                FROM azienda_sanitaria_convenzione ac2
                WHERE ac2.idAziendaSanitaria = a.idAziendaSanitaria
                  AND ac2.idConvenzione IN ($placeholders)
            )
        ";

            $bindings = array_merge($bindings, $ids);
        }

        $sql .= " ORDER BY a.Nome";

        $rows = DB::select($sql, $bindings);

        return collect($rows)->map(function ($a) {
            $a->Convenzioni = trim((string) ($a->Convenzioni ?? '')) !== ''
                ? explode(', ', $a->Convenzioni)
                : [];

            $a->Lotti = trim((string) ($a->Lotti ?? '')) !== ''
                ? explode(', ', $a->Lotti)
                : [];

            return $a;
        });
    }

    public static function getByAnno(int $anno): Collection {
        $idAnno = self::resolveIdAnno($anno);
        $rows = DB::select("
            SELECT *
            FROM " . self::$table . "
            WHERE idAnno = ?
            ORDER BY Nome
        ", [$idAnno]);
        return collect($rows);
    }

    public static function existsForAnno(int $anno): bool {
        $idAnno = self::resolveIdAnno($anno);
        return DB::table(self::$table)->where('idAnno', $idAnno)->exists();
    }

    public static function getById(int $id): ?\stdClass {
        $sql = "SELECT * FROM " . self::$table . " WHERE idAziendaSanitaria = ? LIMIT 1";
        $row = DB::select($sql, [$id]);
        return $row[0] ?? null;
    }

    public static function createSanitaria(array $data): int {
        $anno  = (int) ($data['anno_riferimento'] ?? session('anno_riferimento', now()->year));
        $idAnno = self::resolveIdAnno($anno);

        $sql = "
            INSERT INTO " . self::$table . "
                (idAnno, Nome, Indirizzo, provincia, citta, cap, mail, note, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";

        DB::insert($sql, [
            $idAnno,
            $data['Nome'],
            $data['Indirizzo'] ?? null,
            $data['provincia'] ?? null,
            $data['citta'] ?? null,
            $data['cap'] ?? null,
            $data['mail'] ?? null,
            $data['note'] ?? null,
        ]);

        $id = (int) DB::getPdo()->lastInsertId();

        // pivot (se passato)
        self::syncConvenzioni($id, $data['convenzioni'] ?? []);

        return $id;
    }

    public static function updateSanitaria(int $id, array $data): void {
        // NB: non si cambia idAnno in update (anagrafica appartiene ad un anno)
        $sql = "
            UPDATE " . self::$table . "
            SET Nome = ?, Indirizzo = ?, provincia = ?, citta = ?, cap = ?, mail = ?, note = ?, updated_at = NOW()
            WHERE idAziendaSanitaria = ?
        ";

        DB::update($sql, [
            $data['Nome'],
            $data['Indirizzo'] ?? null,
            $data['provincia'] ?? null,
            $data['citta'] ?? null,
            $data['cap'] ?? null,
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
        DB::delete("DELETE FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?", [$id]);
        DB::delete("DELETE FROM aziende_sanitarie_lotti        WHERE idAziendaSanitaria = ?", [$id]);
        DB::delete("DELETE FROM " . self::$table . "           WHERE idAziendaSanitaria = ?", [$id]);
    }

    public static function getConvenzioni(int $id): array {
        $rows = DB::select(
            "SELECT idConvenzione FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?",
            [$id]
        );
        return array_map(fn($r) => (int) $r->idConvenzione, $rows);
    }

    public static function syncConvenzioni(int $idAzienda, array $convenzioni): void {
        DB::delete("DELETE FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?", [$idAzienda]);

        if (empty($convenzioni)) return;

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
        $anno  = (int) session('anno_riferimento', now()->year);
        $idAnno = self::resolveIdAnno($anno);

        $rows = DB::select("
            SELECT idAziendaSanitaria, Nome
            FROM aziende_sanitarie
            WHERE idAnno = ?
            ORDER BY Nome
        ", [$idAnno]);
        return collect($rows);
    }

    /** Restituisce tutte le aziende dell'anno SENZA filtri, solo per ruoli elevati */
    public static function getAllSenzaFiltri(int $anno): Collection {
        $idAnno = self::resolveIdAnno($anno);


        $sql = "SELECT a.idAziendaSanitaria,
                    a.Nome,
                    a.Indirizzo,
                    a.provincia,
                    a.citta,
                    a.cap,
                    a.mail,
                    cg.Convenzioni,
                    lg.Lotti
                    FROM aziende_sanitarie a
                    LEFT JOIN (
                    SELECT
                    ac.idAziendaSanitaria,
                    GROUP_CONCAT(DISTINCT c.Convenzione ORDER BY c.Convenzione SEPARATOR ', ') AS Convenzioni
                    FROM azienda_sanitaria_convenzione ac
                    JOIN convenzioni c ON c.idConvenzione = ac.idConvenzione
                    WHERE c.idAnno = ?
                    GROUP BY ac.idAziendaSanitaria
                    ) cg ON cg.idAziendaSanitaria = a.idAziendaSanitaria
                    LEFT JOIN (
                    SELECT
                    l.idAziendaSanitaria,
                    GROUP_CONCAT(DISTINCT l.nomeLotto ORDER BY l.nomeLotto SEPARATOR ', ') AS Lotti
                    FROM aziende_sanitarie_lotti l
                    GROUP BY l.idAziendaSanitaria
                    ) lg ON lg.idAziendaSanitaria = a.idAziendaSanitaria
                    WHERE a.idAnno = ?
                    ORDER BY a.Nome
                    ";

        $bindings = [$idAnno, $idAnno];


        $rows = DB::select($sql, $bindings);


        return collect($rows)->map(function ($a) {
            $a->Convenzioni = trim((string) ($a->Convenzioni ?? '')) !== '' ? explode(', ', $a->Convenzioni) : [];
            $a->Lotti = trim((string) ($a->Lotti ?? '')) !== '' ? explode(', ', $a->Lotti) : [];
            return $a;
        });
    }
}

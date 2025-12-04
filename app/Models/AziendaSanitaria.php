<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AziendaSanitaria
{
    protected static string $table = 'aziende_sanitarie';

    /* ============================================================
       Resolve idAnno
       ============================================================ */
    public static function resolveIdAnno(int $anno): int
    {
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

    /* ============================================================
       Fallback indirizzo:
       indirizzo_via vuoto → usa Indirizzo
       ============================================================ */
    private static function selectAddressFields(): string
    {
        return "
            CASE
                WHEN a.indirizzo_via IS NULL OR a.indirizzo_via = ''
                    THEN a.Indirizzo
                ELSE a.indirizzo_via
            END AS indirizzo_via,
            a.indirizzo_civico,
        ";
    }

    /* ============================================================
       LISTA CONVENZIONI + LOTTI
       ============================================================ */
    public static function getAllWithConvenzioni($idConvenzione = null): Collection
    {
        $anno   = session('anno_riferimento', now()->year);
        $idAnno = self::resolveIdAnno($anno);

        $sql = "
            SELECT
                a.idAziendaSanitaria,
                a.Nome,
                " . self::selectAddressFields() . "
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
                JOIN convenzioni c ON c.idConvenzione = ac.idConvenzione AND c.idAnno = ?
                GROUP BY ac.idAziendaSanitaria
            ) cg ON cg.idAziendaSanitaria = a.idAziendaSanitaria

            LEFT JOIN (
                SELECT
                    idAziendaSanitaria,
                    GROUP_CONCAT(nomeLotto ORDER BY nomeLotto SEPARATOR ', ') AS Lotti
                FROM aziende_sanitarie_lotti
                GROUP BY idAziendaSanitaria
            ) lg ON lg.idAziendaSanitaria = a.idAziendaSanitaria

            WHERE a.idAnno = ?
        ";

        $bindings = [$idAnno, $idAnno];

        if (!empty($idConvenzione)) {
            $ids = is_array($idConvenzione) ? $idConvenzione : [$idConvenzione];
            $ph  = implode(',', array_fill(0, count($ids), '?'));

            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM azienda_sanitaria_convenzione x
                    WHERE x.idAziendaSanitaria = a.idAziendaSanitaria
                      AND x.idConvenzione IN ($ph)
                )
            ";

            $bindings = array_merge($bindings, array_map('intval', $ids));
        }

        $sql .= " ORDER BY a.Nome";

        $rows = DB::select($sql, $bindings);

        return collect($rows)->map(function ($r) {
            $r->Convenzioni = $r->Convenzioni ? explode(', ', $r->Convenzioni) : [];
            $r->Lotti       = $r->Lotti       ? explode(', ', $r->Lotti) : [];
            return $r;
        });
    }

    /* ============================================================
       GET BY ANNO
       ============================================================ */
    public static function getByAnno(int $anno): Collection
    {
        $idAnno = self::resolveIdAnno($anno);

        $rows = DB::select("
            SELECT *,
            CASE WHEN indirizzo_via IS NULL OR indirizzo_via = '' THEN Indirizzo ELSE indirizzo_via END AS indirizzo_via
            FROM aziende_sanitarie
            WHERE idAnno = ?
            ORDER BY Nome
        ", [$idAnno]);

        return collect($rows);
    }

    /* ============================================================
       GET BY ID
       ============================================================ */
    public static function getById(int $id): ?\stdClass
    {
        return DB::selectOne("
            SELECT *,
            CASE WHEN indirizzo_via IS NULL OR indirizzo_via = '' THEN Indirizzo ELSE indirizzo_via END AS indirizzo_via
            FROM aziende_sanitarie
            WHERE idAziendaSanitaria = ?
            LIMIT 1
        ", [$id]);
    }

    /* ============================================================
       CREATE
       ============================================================ */
    public static function createSanitaria(array $data): int
    {
        $idAnno = self::resolveIdAnno($data['anno_riferimento'] ?? session('anno_riferimento'));

        DB::insert("
            INSERT INTO aziende_sanitarie
                (idAnno, Nome, indirizzo_via, indirizzo_civico, provincia, citta, cap, mail, note, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ", [
            $idAnno,
            $data['Nome'],
            $data['indirizzo_via'] ?? null,
            $data['indirizzo_civico'] ?? null,
            $data['provincia'] ?? null,
            $data['citta'] ?? null,
            $data['cap'] ?? null,
            $data['mail'] ?? null,
            $data['note'] ?? null,
        ]);

        $id = DB::getPdo()->lastInsertId();

        self::syncConvenzioni($id, $data['convenzioni'] ?? []);

        return (int) $id;
    }

    /* ============================================================
       UPDATE
       ============================================================ */
    public static function updateSanitaria(int $id, array $data): void
    {
        DB::update("
            UPDATE aziende_sanitarie
            SET Nome = ?,
                indirizzo_via = ?,
                indirizzo_civico = ?,
                provincia = ?,
                citta = ?,
                cap = ?,
                mail = ?,
                note = ?,
                updated_at = NOW()
            WHERE idAziendaSanitaria = ?
        ", [
            $data['Nome'],
            $data['indirizzo_via'] ?? null,
            $data['indirizzo_civico'] ?? null,
            $data['provincia'] ?? null,
            $data['citta'] ?? null,
            $data['cap'] ?? null,
            $data['mail'] ?? null,
            $data['note'] ?? null,
            $id
        ]);

        if (isset($data['convenzioni'])) {
            self::syncConvenzioni($id, $data['convenzioni']);
        }
    }

    /* ============================================================
       DELETE
       ============================================================ */
    public static function deleteSanitaria(int $id): void
    {
        DB::delete("DELETE FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?", [$id]);
        DB::delete("DELETE FROM aziende_sanitarie_lotti WHERE idAziendaSanitaria = ?", [$id]);
        DB::delete("DELETE FROM aziende_sanitarie WHERE idAziendaSanitaria = ?", [$id]);
    }

    /* ============================================================
       GET CONVENZIONI
       ============================================================ */
    public static function getConvenzioni(int $id): array
    {
        $rows = DB::select("
            SELECT idConvenzione
            FROM azienda_sanitaria_convenzione
            WHERE idAziendaSanitaria = ?
        ", [$id]);

        return array_map(fn($r) => (int)$r->idConvenzione, $rows);
    }

    /* ============================================================
       SYNC CONVENZIONI
       ============================================================ */
    public static function syncConvenzioni(int $idAzienda, array $convenzioni): void
    {
        DB::delete("DELETE FROM azienda_sanitaria_convenzione WHERE idAziendaSanitaria = ?", [$idAzienda]);

        if (empty($convenzioni)) return;

        $values = [];
        $bind   = [];

        foreach ($convenzioni as $idConv) {
            $values[] = "(?, ?, NOW(), NOW())";
            $bind[]   = $idAzienda;
            $bind[]   = (int)$idConv;
        }

        DB::insert("
            INSERT INTO azienda_sanitaria_convenzione
                (idAziendaSanitaria, idConvenzione, created_at, updated_at)
            VALUES " . implode(', ', $values),
            $bind
        );
    }

    /* ============================================================
       LISTA SENZA FILTRI
       ============================================================ */
    public static function getAllSenzaFiltri(int $anno): Collection
    {
        $idAnno = self::resolveIdAnno($anno);

        $rows = DB::select("
            SELECT
                a.idAziendaSanitaria,
                a.Nome,
                " . self::selectAddressFields() . "
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
                    idAziendaSanitaria,
                    GROUP_CONCAT(nomeLotto ORDER BY nomeLotto SEPARATOR ', ') AS Lotti
                FROM aziende_sanitarie_lotti
                GROUP BY idAziendaSanitaria
            ) lg ON lg.idAziendaSanitaria = a.idAziendaSanitaria
            WHERE a.idAnno = ?
            ORDER BY a.Nome
        ", [$idAnno, $idAnno]);

        return collect($rows)->map(function ($r) {
            $r->Convenzioni = $r->Convenzioni ? explode(', ', $r->Convenzioni) : [];
            $r->Lotti       = $r->Lotti       ? explode(', ', $r->Lotti) : [];
            return $r;
        });
    }
}

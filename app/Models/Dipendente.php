<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class Dipendente
{
    protected const TABLE = 'dipendenti';

    // ⚠️ Adegua se gli ID reali sono diversi
    public const Q_AUTISTA_ID        = 1; // AUTISTA SOCCORRITORE
    public const Q_AMMINISTRATIVO_ID = 7; // IMPIEGATO AMMINISTRATIVO

    /** ---------------------------
     * Helpers (solo SQL)
     * --------------------------- */

    private static function mapQualificheByDipendente(): array
    {
        $rows = DB::select("
            SELECT dq.idDipendente, dq.idQualifica, q.nome
            FROM dipendenti_qualifiche AS dq
            JOIN qualifiche AS q ON q.id = dq.idQualifica
            ORDER BY q.nome
        ");

        $map = [];
        foreach ($rows as $r) {
            $id = (int)$r->idDipendente;
            if (!isset($map[$id])) {
                $map[$id] = ['ids' => [], 'nomi' => []];
            }
            $map[$id]['ids'][]  = (int)$r->idQualifica;
            $map[$id]['nomi'][] = $r->nome;
        }
        return $map;
    }

    private static function baseSelect(): string
    {
        return "
            SELECT
                d.idDipendente,
                d.idAssociazione,
                d.idAnno,
                d.DipendenteNome,
                d.DipendenteCognome,
                d.ContrattoApplicato,
                d.LivelloMansione,
                d.note,
                a.Associazione,
                d.created_at,
                d.updated_at
            FROM ".self::TABLE." AS d
            JOIN associazioni AS a ON a.idAssociazione = d.idAssociazione
        ";
    }

    /** ---------------------------
     * Query principali
     * --------------------------- */

    public static function getAll(int $anno): Collection
    {
        $rows = DB::select(self::baseSelect()." WHERE d.idAnno = ? ORDER BY d.DipendenteCognome", [$anno]);
        $map  = self::mapQualificheByDipendente();

        foreach ($rows as $r) {
            $r->Qualifica = isset($map[$r->idDipendente]) ? implode(', ',$map[$r->idDipendente]['nomi']) : '';
        }
        return collect($rows);
    }

    public static function getByAssociazione(?int $idAssociazione, int $anno): Collection
    {
        $bindings = [$anno];
        $sql = self::baseSelect()." WHERE d.idAnno = ?";

        if (!is_null($idAssociazione)) {
            $sql .= " AND d.idAssociazione = ?";
            $bindings[] = $idAssociazione;
        }

        $sql .= " ORDER BY d.DipendenteCognome";
        $rows = DB::select($sql, $bindings);

        $map  = self::mapQualificheByDipendente();
        foreach ($rows as $r) {
            $r->Qualifica = isset($map[$r->idDipendente]) ? implode(', ',$map[$r->idDipendente]['nomi']) : '';
        }
        return collect($rows);
    }

    public static function getOne(int $idDipendente): ?object
    {
        $row = DB::selectOne("SELECT * FROM ".self::TABLE." WHERE idDipendente = ? LIMIT 1", [$idDipendente]);
        return $row ?: null;
    }

    public static function getAutisti(int $anno): Collection
    {
        $rows = DB::select(self::baseSelect()." WHERE d.idAnno = ? ORDER BY d.DipendenteCognome", [$anno]);

        $ids = DB::select("
            SELECT DISTINCT idDipendente
            FROM dipendenti_qualifiche
            WHERE idQualifica = ?
        ", [self::Q_AUTISTA_ID]);
        $autistiIds = array_map(fn($r) => (int)$r->idDipendente, $ids);

        $map = self::mapQualificheByDipendente();

        $filtered = [];
        foreach ($rows as $r) {
            if (in_array((int)$r->idDipendente, $autistiIds, true)) {
                $r->Qualifica = isset($map[$r->idDipendente]) ? implode(', ',$map[$r->idDipendente]['nomi']) : '';
                $filtered[] = $r;
            }
        }
        return collect($filtered);
    }

    public static function getAmministrativi(int $anno): Collection
    {
        $rows = DB::select(self::baseSelect()." WHERE d.idAnno = ? ORDER BY d.DipendenteCognome", [$anno]);

        $ids = DB::select("
            SELECT DISTINCT idDipendente
            FROM dipendenti_qualifiche
            WHERE idQualifica = ?
        ", [self::Q_AMMINISTRATIVO_ID]);
        $ammIds = array_map(fn($r) => (int)$r->idDipendente, $ids);

        $map = self::mapQualificheByDipendente();

        $filtered = [];
        foreach ($rows as $r) {
            if (in_array((int)$r->idDipendente, $ammIds, true)) {
                $r->Qualifica = isset($map[$r->idDipendente]) ? implode(', ',$map[$r->idDipendente]['nomi']) : '';
                $filtered[] = $r;
            }
        }
        return collect($filtered);
    }

    public static function getAltri(int $anno): Collection
    {
        // Tutti
        $rows = DB::select(self::baseSelect()." WHERE d.idAnno = ? ORDER BY d.DipendenteCognome", [$anno]);

        // Autisti ids
        $aut = DB::select("
            SELECT DISTINCT idDipendente
            FROM dipendenti_qualifiche
            WHERE idQualifica = ?
        ", [self::Q_AUTISTA_ID]);
        $autistiIds = array_map(fn($r) => (int)$r->idDipendente, $aut);

        $map = self::mapQualificheByDipendente();

        $filtered = [];
        foreach ($rows as $r) {
            if (!in_array((int)$r->idDipendente, $autistiIds, true)) {
                $r->Qualifica = isset($map[$r->idDipendente]) ? implode(', ',$map[$r->idDipendente]['nomi']) : '';
                $filtered[] = $r;
            }
        }
        return collect($filtered);
    }

    /** ---------------------------
     * Lookup tabelle di servizio
     * --------------------------- */

    public static function getLivelliMansione(): Collection
    {
        return collect(DB::select("SELECT id, nome FROM livello_mansione ORDER BY nome"));
    }

    public static function getLivelliMansioneByDipendente(int $idDipendente): array
    {
        $rows = DB::select("
            SELECT idLivelloMansione
            FROM dipendenti_livelli_mansione
            WHERE idDipendente = ?
        ", [$idDipendente]);

        return array_map(fn($r) => (int)$r->idLivelloMansione, $rows);
    }

    public static function getQualifiche(): Collection
    {
        return collect(DB::select("SELECT id, nome FROM qualifiche ORDER BY nome"));
    }

    public static function getQualificheByDipendente(int $idDipendente): array
    {
        $rows = DB::select("
            SELECT idQualifica
            FROM dipendenti_qualifiche
            WHERE idDipendente = ?
        ", [$idDipendente]);

        return array_map(fn($r) => (int)$r->idQualifica, $rows);
    }

    public static function getNomiQualifiche(int $idDipendente): string
    {
        $rows = DB::select("
            SELECT q.nome
            FROM dipendenti_qualifiche AS dq
            JOIN qualifiche AS q ON q.id = dq.idQualifica
            WHERE dq.idDipendente = ?
            ORDER BY q.nome
        ", [$idDipendente]);

        return implode(', ', array_map(fn($r) => $r->nome, $rows));
    }

    public static function getContrattiApplicati(): Collection
    {
        return collect(DB::select("SELECT nome FROM contratti_applicati ORDER BY nome"));
    }

    public static function getAnni(): Collection
    {
        return collect(DB::select("SELECT idAnno, anno FROM anni ORDER BY anno DESC"));
    }

    public static function getAssociazioni($user, bool $isImpersonating): Collection
    {
        if ($user->hasAnyRole(['SuperAdmin','Admin','Supervisor']) && !$isImpersonating) {
            $rows = DB::select("
                SELECT idAssociazione, Associazione
                FROM associazioni
                WHERE deleted_at IS NULL AND idAssociazione <> 1
                ORDER BY Associazione
            ");
        } else {
            $rows = DB::select("
                SELECT idAssociazione, Associazione
                FROM associazioni
                WHERE deleted_at IS NULL AND idAssociazione <> 1 AND idAssociazione = ?
                ORDER BY Associazione
            ", [$user->IdAssociazione]);
        }
        return collect($rows);
    }

    public static function getAutistiEBarellieri(int $anno, $idAssociazione = null): Collection
    {
        $sql = "
            SELECT
                d.idDipendente,
                d.DipendenteNome,
                d.DipendenteCognome,
                d.idAssociazione,
                a.Associazione
            FROM dipendenti AS d
            JOIN dipendenti_qualifiche AS dq ON dq.idDipendente = d.idDipendente
            JOIN associazioni AS a ON a.idAssociazione = d.idAssociazione
            WHERE d.idAnno = ? AND dq.idQualifica = ?
        ";

        $bindings = [$anno, self::Q_AUTISTA_ID];

        if (!is_null($idAssociazione)) {
            $sql .= " AND d.idAssociazione = ?";
            $bindings[] = $idAssociazione;
        }

        $sql .= " ORDER BY d.DipendenteCognome";

        return collect(DB::select($sql, $bindings));
    }

    public static function getCognomeNome(int $idDipendente): ?object
    {
        $row = DB::selectOne("
            SELECT DipendenteNome, DipendenteCognome
            FROM dipendenti
            WHERE idDipendente = ?
            LIMIT 1
        ", [$idDipendente]);

        return $row ?: null;
    }

    /** ---------------------------
     * CRUD (no Eloquent)
     * --------------------------- */

    public static function storeDipendente(array $data)
    {
        // Normalizza chiave legacy
        if (isset($data['IdAssociazione']) && !isset($data['idAssociazione'])) {
            $data['idAssociazione'] = $data['IdAssociazione'];
            unset($data['IdAssociazione']);
        }

        $qualifiche = $data['Qualifica'] ?? [];
        unset($data['Qualifica']);

        $now   = now();
        $uid   = auth()->id();

        $sql = "
            INSERT INTO ".self::TABLE."
            (idAssociazione, idAnno, DipendenteNome, DipendenteCognome, ContrattoApplicato, LivelloMansione, note, created_at, updated_at, created_by, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        DB::insert($sql, [
            $data['idAssociazione'],
            $data['idAnno'],
            $data['DipendenteNome'],
            $data['DipendenteCognome'],
            $data['ContrattoApplicato'] ?? null,
            $data['LivelloMansione']   ?? null,
            $data['note']              ?? null,
            $now, $now, $uid, $uid
        ]);

        $id = (int) DB::getPdo()->lastInsertId();

        // Pivot qualifiche
        if (!empty($qualifiche)) {
            $pvSql = "INSERT INTO dipendenti_qualifiche (idDipendente, idQualifica, created_at, updated_at) VALUES (?, ?, ?, ?)";
            foreach (array_unique($qualifiche) as $q) {
                DB::insert($pvSql, [$id, (int)$q, $now, $now]);
            }
        }

        return redirect()->route('dipendenti.index')->with('success', 'Dipendente creato correttamente.');
    }

    public static function updateDipendente(int $id, array $data)
    {
        if (isset($data['IdAssociazione']) && !isset($data['idAssociazione'])) {
            $data['idAssociazione'] = $data['IdAssociazione'];
            unset($data['IdAssociazione']);
        }

        $qualifiche = $data['Qualifica'] ?? [];
        unset($data['Qualifica']);

        $now = now();
        $uid = auth()->id();

        $sql = "
            UPDATE ".self::TABLE."
            SET
                idAssociazione = ?,
                idAnno = ?,
                DipendenteNome = ?,
                DipendenteCognome = ?,
                ContrattoApplicato = ?,
                LivelloMansione = ?,
                note = ?,
                updated_at = ?,
                updated_by = ?
            WHERE idDipendente = ?
        ";

        DB::update($sql, [
            $data['idAssociazione'],
            $data['idAnno'],
            $data['DipendenteNome'],
            $data['DipendenteCognome'],
            $data['ContrattoApplicato'] ?? null,
            $data['LivelloMansione']   ?? null,
            $data['note']              ?? null,
            $now, $uid, $id
        ]);

        // Reset & reinsert pivot qualifiche
        DB::delete("DELETE FROM dipendenti_qualifiche WHERE idDipendente = ?", [$id]);

        if (!empty($qualifiche)) {
            $pvSql = "INSERT INTO dipendenti_qualifiche (idDipendente, idQualifica, created_at, updated_at) VALUES (?, ?, ?, ?)";
            foreach (array_unique($qualifiche) as $q) {
                DB::insert($pvSql, [$id, (int)$q, $now, $now]);
            }
        }

        return redirect()->route('dipendenti.index')->with('success', 'Dipendente aggiornato correttamente.');
    }

    public static function eliminaDipendente(int $id): int
    {
        // Pulisci pivot
        DB::delete("DELETE FROM dipendenti_qualifiche WHERE idDipendente = ?", [$id]);
        DB::delete("DELETE FROM dipendenti_livelli_mansione WHERE idDipendente = ?", [$id]);

        // Delete principale
        return DB::delete("DELETE FROM ".self::TABLE." WHERE idDipendente = ?", [$id]);
    }

    /** ---------------------------
     * Duplicazione anno (copia pivot)
     * --------------------------- */
    public static function duplicaAnno(int $idAssociazione, int $fromAnno, int $toAnno): int
    {
        $src = DB::select("
            SELECT *
            FROM ".self::TABLE."
            WHERE idAssociazione = ? AND idAnno = ?
        ", [$idAssociazione, $fromAnno]);

        if (empty($src)) return 0;

        $now = now();
        $uid = auth()->id();
        $count = 0;

        DB::transaction(function () use ($src, $idAssociazione, $toAnno, $now, $uid, &$count) {
            foreach ($src as $d) {
                DB::insert("
                    INSERT INTO ".self::TABLE."
                    (idAssociazione, idAnno, DipendenteNome, DipendenteCognome, ContrattoApplicato, LivelloMansione, note, created_at, updated_at, created_by, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [
                    $idAssociazione, $toAnno,
                    $d->DipendenteNome, $d->DipendenteCognome,
                    $d->ContrattoApplicato, $d->LivelloMansione,
                    $d->note, $now, $now, $uid, $uid
                ]);

                $newId = (int) DB::getPdo()->lastInsertId();

                // Qualifiche
                $q = DB::select("SELECT idQualifica FROM dipendenti_qualifiche WHERE idDipendente = ?", [$d->idDipendente]);
                foreach ($q as $row) {
                    DB::insert("
                        INSERT INTO dipendenti_qualifiche (idDipendente, idQualifica, created_at, updated_at)
                        VALUES (?, ?, ?, ?)
                    ", [$newId, (int)$row->idQualifica, $now, $now]);
                }

                // Livelli (se usi la pivot)
                $lv = DB::select("SELECT idLivelloMansione FROM dipendenti_livelli_mansione WHERE idDipendente = ?", [$d->idDipendente]);
                foreach ($lv as $row) {
                    DB::insert("
                        INSERT INTO dipendenti_livelli_mansione (idDipendente, idLivelloMansione, created_at, updated_at)
                        VALUES (?, ?, ?, ?)
                    ", [$newId, (int)$row->idLivelloMansione, $now, $now]);
                }

                $count++;
            }
        });

        return $count;
    }
}

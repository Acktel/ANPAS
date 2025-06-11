<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Dipendente {
    protected const TABLE = 'dipendenti';

    /**
     * Elenco completo dipendenti di tutte le associazioni per anno.
     */
    public static function getAll(int $anno) {
        $sql = "
            SELECT
                d.idDipendente,
                s.Associazione,
                d.idAnno,
                d.DipendenteNome,
                d.DipendenteCognome,
                d.Qualifica,
                d.ContrattoApplicato,
                d.LivelloMansione,
                d.created_at
            FROM dipendenti d
            JOIN associazioni s ON d.idAssociazione = s.idAssociazione
            WHERE d.idAnno = :anno
            ORDER BY s.Associazione, d.DipendenteCognome, d.DipendenteNome
        ";

        return collect(DB::select($sql, ['anno' => $anno]));
    }

    /**
     * Elenco dipendenti per associazione e anno.
     */
    public static function getByAssociazione(int $idAssociazione, int $anno) {
        $sql = "
            SELECT
                d.idDipendente,
                s.Associazione,
                d.idAnno,
                d.DipendenteNome,
                d.DipendenteCognome,
                d.Qualifica,
                d.ContrattoApplicato,
                d.LivelloMansione,
                d.created_at
            FROM dipendenti d
            JOIN associazioni s ON d.idAssociazione = s.idAssociazione
            WHERE d.idAssociazione = :idAssociazione AND d.idAnno = :anno
            ORDER BY d.DipendenteCognome, d.DipendenteNome
        ";

        return collect(DB::select($sql, [
            'idAssociazione' => $idAssociazione,
            'anno' => $anno,
        ]));
    }

    /**
     * Dipendenti con qualifica contenente 'AUTISTA' per anno.
     */
    public static function getAutisti(int $anno) {
        $sql = "
            SELECT
                d.idDipendente,
                s.Associazione,
                d.idAnno,
                d.DipendenteNome,
                d.DipendenteCognome,
                d.Qualifica,
                d.ContrattoApplicato,
                d.LivelloMansione,
                d.created_at
            FROM dipendenti d
            JOIN associazioni s ON d.idAssociazione = s.idAssociazione
            WHERE d.idAnno = :anno AND FIND_IN_SET('AUTISTA', d.Qualifica) > 0
            ORDER BY d.DipendenteCognome, d.DipendenteNome
        ";

        return collect(DB::select($sql, ['anno' => $anno]));
    }

    /**
     * Dipendenti la cui qualifica NON contiene 'AUTISTA' per anno.
     */
    public static function getAltri(int $anno) {
        $sql = "
            SELECT
                d.idDipendente,
                s.Associazione,
                d.idAnno,
                d.DipendenteNome,
                d.DipendenteCognome,
                d.Qualifica,
                d.ContrattoApplicato,
                d.LivelloMansione,
                d.created_at
            FROM dipendenti d
            JOIN associazioni s ON d.idAssociazione = s.idAssociazione
            WHERE d.idAnno = :anno
            AND d.Qualifica NOT LIKE '%AUTISTA%'
            ORDER BY d.DipendenteCognome, d.DipendenteNome
        ";

        return collect(DB::select($sql, ['anno' => $anno]));
    }

    /**
     * Conta quanti dipendenti hanno una determinata qualifica.
     */
    public static function countByQualifica(string $qualifica): int {
        $sql = "SELECT COUNT(*) AS cnt FROM dipendenti WHERE FIND_IN_SET(:qual, Qualifica) > 0";
        $row = DB::selectOne($sql, ['qual' => $qualifica]);

        return (int) $row->cnt;
    }

    /**
     * Dettaglio singolo dipendente.
     */
    public static function getById(int $idDipendente) {
        $sql = "
            SELECT
                idDipendente,
                idAssociazione,
                idAnno,
                DipendenteNome,
                DipendenteCognome,
                Qualifica,
                ContrattoApplicato,
                LivelloMansione,
                created_at,
                updated_at
            FROM dipendenti
            WHERE idDipendente = :idDipendente
            LIMIT 1
        ";

        $res = DB::select($sql, ['idDipendente' => $idDipendente]);
        return count($res) ? $res[0] : null;
    }

    /**
     * Crea un nuovo dipendente.
     */
    public static function createDipendente(array $data): int {
        $now = Carbon::now()->toDateTimeString();

        $sql = "
            INSERT INTO dipendenti (
                idAssociazione,
                idAnno,
                DipendenteNome,
                DipendenteCognome,
                Qualifica,
                ContrattoApplicato,
                LivelloMansione,
                created_at,
                updated_at
            ) VALUES (
                :idAssociazione,
                :idAnno,
                :DipendenteNome,
                :DipendenteCognome,
                :Qualifica,
                :ContrattoApplicato,
                :LivelloMansione,
                :created_at,
                :updated_at
            )
        ";

        DB::insert($sql, [
            'idAssociazione'      => $data['idAssociazione'],
            'idAnno'              => $data['idAnno'],
            'DipendenteNome'      => $data['DipendenteNome'],
            'DipendenteCognome'   => $data['DipendenteCognome'],
            'Qualifica'           => $data['Qualifica'],
            'ContrattoApplicato'  => $data['ContrattoApplicato'],
            'LivelloMansione'     => $data['LivelloMansione'],
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        return DB::getPdo()->lastInsertId();
    }

    /**
     * Aggiorna un dipendente.
     */
    public static function updateDipendente(int $idDipendente, array $data): void {
        $now = Carbon::now()->toDateTimeString();

        $sql = "
            UPDATE dipendenti
            SET
                idAssociazione = :idAssociazione,
                idAnno = :idAnno,
                DipendenteNome = :DipendenteNome,
                DipendenteCognome = :DipendenteCognome,
                Qualifica = :Qualifica,
                ContrattoApplicato = :ContrattoApplicato,
                LivelloMansione = :LivelloMansione,
                updated_at = :updated_at
            WHERE idDipendente = :idDipendente
        ";

        DB::update($sql, [
            'idAssociazione'      => $data['idAssociazione'],
            'idAnno'              => $data['idAnno'],
            'DipendenteNome'      => $data['DipendenteNome'],
            'DipendenteCognome'   => $data['DipendenteCognome'],
            'Qualifica'           => $data['Qualifica'],
            'ContrattoApplicato'  => $data['ContrattoApplicato'],
            'LivelloMansione'     => $data['LivelloMansione'],
            'updated_at'          => $now,
            'idDipendente'        => $idDipendente,
        ]);
    }

    /**
     * Elimina un dipendente.
     */
    public static function deleteDipendente(int $idDipendente): void {
        DB::delete("DELETE FROM dipendenti WHERE idDipendente = :idDipendente", [
            'idDipendente' => $idDipendente
        ]);
    }
}

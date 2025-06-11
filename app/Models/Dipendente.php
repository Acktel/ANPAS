<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class Dipendente {
    protected const TABLE = 'dipendenti';

    public static function getAll() {
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
            FROM dipendenti AS d
            JOIN associazioni AS s
              ON d.idAssociazione = s.idAssociazione
            ORDER BY s.Associazione, d.idAnno DESC, d.DipendenteCognome, d.DipendenteNome
        ";

        // CORRETTO: passo direttamente la stringa $sql
        return collect(DB::select($sql));
    }

    public static function getByAssociazione(int $idAssociazione) {
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
            FROM dipendenti AS d
            JOIN associazioni AS s
              ON d.idAssociazione = s.idAssociazione
            WHERE d.idAssociazione = :idAssociazione
            ORDER BY d.idAnno DESC, d.DipendenteCognome, d.DipendenteNome
        ";

        // CORRETTO: passo la stringa $sql e i parametri
        return collect(
            DB::select($sql, ['idAssociazione' => $idAssociazione])
        );
    }

    public static function getAutisti() {
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
            FROM dipendenti AS d
            JOIN associazioni AS s
              ON d.idAssociazione = s.idAssociazione
            WHERE FIND_IN_SET('AUTISTA', d.Qualifica) > 0
            ORDER BY d.idAnno DESC, d.DipendenteCognome, d.DipendenteNome
        ";

        return collect(DB::select($sql));
    }

    public static function getAltri() {
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
            FROM dipendenti AS d
            JOIN associazioni AS s
              ON d.idAssociazione = s.idAssociazione
            WHERE FIND_IN_SET('AUTISTA', d.Qualifica) = 0
            ORDER BY d.idAnno DESC, d.DipendenteCognome, d.DipendenteNome
        ";

        return collect(DB::select($sql));
    }

    public static function countByQualifica(string $qualifica): int {
        $sql = "
            SELECT COUNT(*) AS cnt
            FROM dipendenti
            WHERE FIND_IN_SET(:qual, Qualifica) > 0
        ";

        $row = DB::selectOne($sql, ['qual' => $qualifica]);

        return $row->cnt;
    }

    public static function getById(int $idDipendente) {
        $sql = "
            SELECT
                d.idDipendente,
                d.idAssociazione,
                d.idAnno,
                d.DipendenteNome,
                d.DipendenteCognome,
                d.Qualifica,
                d.ContrattoApplicato,
                d.LivelloMansione,
                d.created_at,
                d.updated_at
            FROM dipendenti AS d
            WHERE d.idDipendente = :idDipendente
            LIMIT 1
        ";

        $res = DB::select($sql, ['idDipendente' => $idDipendente]);

        return count($res) ? $res[0] : null;
    }

    public static function createDipendente(array $data): int {
        $now = Carbon::now()->toDateTimeString();

        $sql = "
            INSERT INTO dipendenti
                (
                  idAssociazione,
                  idAnno,
                  DipendenteNome,
                  DipendenteCognome,
                  Qualifica,
                  ContrattoApplicato,
                  LivelloMansione,
                  created_at,
                  updated_at
                )
            VALUES
                (
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

    public static function updateDipendente(int $idDipendente, array $data): void {
        $now = Carbon::now()->toDateTimeString();

        $sql = "
            UPDATE dipendenti
            SET
                idAssociazione      = :idAssociazione,
                idAnno              = :idAnno,
                DipendenteNome      = :DipendenteNome,
                DipendenteCognome   = :DipendenteCognome,
                Qualifica           = :Qualifica,
                ContrattoApplicato  = :ContrattoApplicato,
                LivelloMansione     = :LivelloMansione,
                updated_at          = :updated_at
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

    public static function deleteDipendente(int $idDipendente): void {
        $sql = "
            DELETE FROM dipendenti
            WHERE idDipendente = :idDipendente
        ";

        DB::delete($sql, ['idDipendente' => $idDipendente]);
    }
}

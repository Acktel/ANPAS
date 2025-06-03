<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Automezzo
{
    protected const TABLE = 'automezzi';

    /**
     * Restituisce tutti gli automezzi con le associazioni e gli anni.
     *
     * @return array<\stdClass>
     */
    public static function allWithRelations(): array
    {
        return DB::select(
            'SELECT 
                a.idAutomezzo,
                a.idAssociazione,
                asso.Associazione,
                a.idAnno,
                anno.Anno,
                a.Automezzo,
                a.Targa,
                a.CodiceIdentificativo,
                a.AnnoPrimaImmatricolazione,
                a.Modello,
                a.TipoVeicolo,
                a.KmRiferimento,
                a.KmTotali,
                a.TipoCarburante,
                a.DataUltimaAutorizzazioneSanitaria,
                a.DataUltimoCollaudo,
                a.created_at,
                a.updated_at
             FROM ' . self::TABLE . ' a
             JOIN associazioni asso ON a.idAssociazione = asso.idAssociazione
             JOIN anni anno           ON a.idAnno          = anno.idAnno
             ORDER BY a.Automezzo'
        );
    }

    /**
     * Restituisce un singolo automezzo (tutti i campi).
     */
    public static function find(int $id): ?\stdClass
    {
        return DB::selectOne(
            'SELECT
                *
             FROM ' . self::TABLE . '
             WHERE idAutomezzo = ?',
            [$id]
        );
    }

    /**
     * Inserisce un nuovo automezzo.
     *
     * $data deve contenere chiavi:
     * idAssociazione, idAnno, Automezzo, Targa,
     * CodiceIdentificativo, AnnoPrimaImmatricolazione,
     * Modello, TipoVeicolo, KmRiferimento, KmTotali,
     * TipoCarburante, DataUltimaAutorizzazioneSanitaria,
     * DataUltimoCollaudo
     */
    public static function create(array $data): void
    {
        DB::insert(
            'INSERT INTO ' . self::TABLE . ' (
                idAssociazione,
                idAnno,
                Automezzo,
                Targa,
                CodiceIdentificativo,
                AnnoPrimaImmatricolazione,
                Modello,
                TipoVeicolo,
                KmRiferimento,
                KmTotali,
                TipoCarburante,
                DataUltimaAutorizzazioneSanitaria,
                DataUltimoCollaudo,
                created_at,
                updated_at
            ) VALUES (
                :idAssociazione,
                :idAnno,
                :Automezzo,
                :Targa,
                :CodiceIdentificativo,
                :AnnoPrimaImmatricolazione,
                :Modello,
                :TipoVeicolo,
                :KmRiferimento,
                :KmTotali,
                :TipoCarburante,
                :DataUltimaAutorizzazioneSanitaria,
                :DataUltimoCollaudo,
                NOW(),
                NOW()
            )',
            $data
        );
    }

    /**
     * Aggiorna un automezzo esistente.
     *
     * $data come in create(), tranne created_at.
     */
    public static function update(int $id, array $data): void
    {
        $data['idAutomezzo'] = $id;

        DB::update(
            'UPDATE ' . self::TABLE . '
             SET
                idAssociazione                         = :idAssociazione,
                idAnno                                 = :idAnno,
                Automezzo                              = :Automezzo,
                Targa                                  = :Targa,
                CodiceIdentificativo                   = :CodiceIdentificativo,
                AnnoPrimaImmatricolazione              = :AnnoPrimaImmatricolazione,
                Modello                                = :Modello,
                TipoVeicolo                            = :TipoVeicolo,
                KmRiferimento                          = :KmRiferimento,
                KmTotali                               = :KmTotali,
                TipoCarburante                         = :TipoCarburante,
                DataUltimaAutorizzazioneSanitaria      = :DataUltimaAutorizzazioneSanitaria,
                DataUltimoCollaudo                     = :DataUltimoCollaudo,
                updated_at                             = NOW()
             WHERE idAutomezzo = :idAutomezzo',
            $data
        );
    }

    /**
     * Elimina l’automezzo.
     */
    public static function delete(int $id): void
    {
        DB::delete(
            'DELETE FROM ' . self::TABLE . ' WHERE idAutomezzo = ?',
            [$id]
        );
    }

    /**
     * Dati per i form: tutte le associazioni.
     *
     * @return array<\stdClass>
     */
    public static function listaAssociazioni(): array
    {
        return DB::select('SELECT idAssociazione, Associazione FROM associazioni');
    }

    /**
     * Dati per i form: tutti gli anni.
     *
     * @return array<\stdClass>
     */
    public static function listaAnni(): array
    {
        return DB::select('SELECT idAnno, Anno FROM anni');
    }
}

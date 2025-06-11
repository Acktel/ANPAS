<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Automezzo
{
    protected const TABLE = 'automezzi';
    /**
     * Restituisce tutti gli automezzi (per l’indice, con eventuale join ad associazioni/anni se serve).
     */
public static function getAll(?int $anno = null): Collection
{
    $anno = $anno ?? session('anno_riferimento', now()->year);

    return DB::table('automezzi as a')
        ->join('associazioni as s', 'a.idAssociazione', '=', 's.idAssociazione')
        ->join('anni as y', 'a.idAnno', '=', 'y.idAnno')
        ->select([
            'a.idAutomezzo',
            's.Associazione',
            'y.anno',
            'a.Automezzo',
            'a.Targa',
            'a.CodiceIdentificativo',
            'a.AnnoPrimaImmatricolazione',
            'a.Modello',
            'a.TipoVeicolo',
            'a.KmRiferimento',
            'a.KmTotali',
            'a.TipoCarburante',
            'a.DataUltimaAutorizzazioneSanitaria',
            'a.DataUltimoCollaudo',
            'a.created_at',
        ])
        ->where('a.idAnno', $anno)
        ->orderBy('s.Associazione')
        ->orderBy('a.Automezzo')
        ->get();
}


    /**
     * Crea un nuovo record in `automezzi` e restituisce l’id.
     */
    public static function createAutomezzo(array $data): int
    {
        return DB::table('automezzi')->insertGetId([
            'idAssociazione'                 => $data['idAssociazione'],
            'idAnno'                         => $data['idAnno'],
            'Automezzo'                      => $data['Automezzo'],
            'Targa'                          => $data['Targa'],
            'CodiceIdentificativo'           => $data['CodiceIdentificativo'],
            'AnnoPrimaImmatricolazione'      => $data['AnnoPrimaImmatricolazione'],
            'Modello'                        => $data['Modello'],
            'TipoVeicolo'                    => $data['TipoVeicolo'],
            'KmRiferimento'                  => $data['KmRiferimento'],
            'KmTotali'                       => $data['KmTotali'],
            'TipoCarburante'                 => $data['TipoCarburante'],
            'DataUltimaAutorizzazioneSanitaria' => $data['DataUltimaAutorizzazioneSanitaria'],
            'DataUltimoCollaudo'             => $data['DataUltimoCollaudo'],
            'created_at'                     => Carbon::now(),
            'updated_at'                     => Carbon::now(),
        ], 'idAutomezzo');
    }

    /**
     * Recupera un singolo automezzo.
     */
    public static function getById(int $idAutomezzo)
    {
        return DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->first();
    }

    /**
     * Aggiorna un automezzo esistente.
     */
    public static function updateAutomezzo(int $idAutomezzo, array $data): void
    {
        DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->update([
                'idAssociazione'                 => $data['idAssociazione'],
                'idAnno'                         => $data['idAnno'],
                'Automezzo'                      => $data['Automezzo'],
                'Targa'                          => $data['Targa'],
                'CodiceIdentificativo'           => $data['CodiceIdentificativo'],
                'AnnoPrimaImmatricolazione'      => $data['AnnoPrimaImmatricolazione'],
                'Modello'                        => $data['Modello'],
                'TipoVeicolo'                    => $data['TipoVeicolo'],
                'KmRiferimento'                  => $data['KmRiferimento'],
                'KmTotali'                       => $data['KmTotali'],
                'TipoCarburante'                 => $data['TipoCarburante'],
                'DataUltimaAutorizzazioneSanitaria' => $data['DataUltimaAutorizzazioneSanitaria'],
                'DataUltimoCollaudo'             => $data['DataUltimoCollaudo'],
                'updated_at'                     => Carbon::now(),
            ]);
    }

    /**
     * Elimina un automezzo (e tutte le righe collegate in automezzi_km).
     */
    public static function deleteAutomezzo(int $idAutomezzo): void
    {
        // Prima eliminiamo tutte le righe di automezzi_km
        AutomezzoKm::deleteByAutomezzo($idAutomezzo);

        // Poi eliminiamo il record principale
        DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->delete();
    }

    /**
     * Recupera tutti gli automezzi di una data associazione (senza join su `anni`).
     *
     * @param int $idAssociazione
     * @return \Illuminate\Support\Collection
     */
public static function getByAssociazione(int $idAssociazione, ?int $anno = null): Collection
{
    $anno = $anno ?? session('anno_riferimento', now()->year);

    return DB::table(self::TABLE . ' as a')
        ->join('associazioni as s', 'a.idAssociazione', '=', 's.idAssociazione')
        ->select([
            'a.idAutomezzo',
            's.Associazione',
            'a.idAnno',
            'a.Automezzo',
            'a.Targa',
            'a.CodiceIdentificativo',
            'a.AnnoPrimaImmatricolazione',
            'a.Modello',
            'a.TipoVeicolo',
            'a.KmRiferimento',
            'a.KmTotali',
            'a.TipoCarburante',
            'a.DataUltimaAutorizzazioneSanitaria',
            'a.DataUltimoCollaudo',
        ])
        ->where('a.idAssociazione', $idAssociazione)
        ->where('a.idAnno', $anno)
        ->orderBy('a.idAnno', 'desc')
        ->orderBy('a.Automezzo')
        ->get();
}

}

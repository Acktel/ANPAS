<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Automezzo {
    protected const TABLE = 'automezzi';

    /**
     * Restituisce tutti gli automezzi (per l’indice), con join a km_riferimento se presente.
     */
    public static function getAll(?int $anno = null): Collection {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table('automezzi as a')
            ->join('associazioni as s', 'a.idAssociazione', '=', 's.idAssociazione')
            ->join('anni as y', 'a.idAnno', '=', 'y.idAnno')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('a.idAutomezzo', '=', 'km.idAutomezzo')
                    ->where('km.idAnno', '=', $anno);
            })
            ->select([
                'a.idAutomezzo',
                's.Associazione',
                'y.anno',
                'a.Automezzo',
                'a.Targa',
                'a.CodiceIdentificativo',
                'a.AnnoPrimaImmatricolazione',
                'a.AnnoAcquisto',
                'a.Modello',
                'a.TipoVeicolo',
                'km.KmRiferimento',
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
    public static function createAutomezzo(array $data): int {
        return DB::table('automezzi')->insertGetId([
            'idAssociazione'                 => $data['idAssociazione'],
            'idAnno'                         => $data['idAnno'],
            'Automezzo'                      => $data['Automezzo'],
            'Targa'                          => $data['Targa'],
            'CodiceIdentificativo'           => $data['CodiceIdentificativo'],
            'AnnoPrimaImmatricolazione'      => $data['AnnoPrimaImmatricolazione'],
            'AnnoAcquisto'                   => $data['AnnoAcquisto'] ?? null,
            'Modello'                        => $data['Modello'],
            'TipoVeicolo'                    => $data['TipoVeicolo'],
            'KmTotali'                       => $data['KmTotali'],
            'TipoCarburante'                 => $data['TipoCarburante'],
            'DataUltimaAutorizzazioneSanitaria' => $data['DataUltimaAutorizzazioneSanitaria'],
            'DataUltimoCollaudo'             => $data['DataUltimoCollaudo'],
            'created_at'                     => Carbon::now(),
            'updated_at'                     => Carbon::now(),
        ], 'idAutomezzo');
    }

    /**
     * Recupera un singolo automezzo, con join al km_riferimento per l’anno attuale.
     */
    public static function getById(int $idAutomezzo, ?int $anno = null) {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table('automezzi as a')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('a.idAutomezzo', '=', 'km.idAutomezzo')
                    ->where('km.idAnno', '=', $anno);
            })
            ->where('a.idAutomezzo', $idAutomezzo)
            ->select([
                'a.*',
                'km.KmRiferimento as KmRiferimento'
            ])
            ->first();
    }

    /**
     * Aggiorna un automezzo esistente.
     */
    public static function updateAutomezzo(int $idAutomezzo, array $data): void {
        DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->update([
                'idAssociazione'                 => $data['idAssociazione'],
                'idAnno'                         => $data['idAnno'],
                'Automezzo'                      => $data['Automezzo'],
                'Targa'                          => $data['Targa'],
                'CodiceIdentificativo'           => $data['CodiceIdentificativo'],
                'AnnoPrimaImmatricolazione'      => $data['AnnoPrimaImmatricolazione'],
                'AnnoAcquisto'                   => $data['AnnoAcquisto'] ?? null,
                'Modello'                        => $data['Modello'],
                'TipoVeicolo'                    => $data['TipoVeicolo'],
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
    public static function deleteAutomezzo(int $idAutomezzo): void {
        AutomezzoKm::deleteByAutomezzo($idAutomezzo);
        DB::table('automezzi')->where('idAutomezzo', $idAutomezzo)->delete();
    }

    /**
     * Recupera tutti gli automezzi di una data associazione (con km_riferimento).
     */
    public static function getByAssociazione(int $idAssociazione, ?int $anno = null): Collection {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table(self::TABLE . ' as a')
            ->join('associazioni as s', 'a.idAssociazione', '=', 's.idAssociazione')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('a.idAutomezzo', '=', 'km.idAutomezzo')
                    ->where('km.idAnno', '=', $anno);
            })
            ->select([
                'a.idAutomezzo',
                's.Associazione',
                'a.idAnno',
                'a.Automezzo',
                'a.Targa',
                'a.CodiceIdentificativo',
                'a.AnnoPrimaImmatricolazione',
                'a.AnnoAcquisto',
                'a.Modello',
                'a.TipoVeicolo',
                'km.KmRiferimento',
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

    /**
     * Relazione (opzionale) - tutti i km riferiti a un automezzo.
     */
    public function kmRiferimenti() {
        return $this->hasMany(AutomezzoKmRiferimento::class, 'idAutomezzo', 'idAutomezzo');
    }

    /**
     * Accessor utile - ottiene km riferiti a un anno specifico.
     */
    public function getKmRiferimentoAnno($anno = null) {
        $anno = $anno ?? session('anno_riferimento', now()->year);
        return $this->kmRiferimenti()->where('idAnno', $anno)->value('KmRiferimento');
    }
    public static function getForDataTable(int $anno, ?User $user) {
        $query = DB::table('automezzi as a')
            ->join('associazioni as ass', 'ass.idAssociazione', '=', 'a.idAssociazione')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('km.idAutomezzo', '=', 'a.idAutomezzo')
                    ->where('km.idAnno', $anno);
            })
            ->where('a.idAnno', $anno);

        if (! $user->isSuperAdmin()) {
            $query->where('a.idAssociazione', $user->IdAssociazione);
        }

        return $query->select([
            'a.idAutomezzo',
            'ass.Associazione',
            'a.idAnno',
            'a.Automezzo',
            'a.Targa',
            'a.CodiceIdentificativo',
            'a.AnnoPrimaImmatricolazione',
            'a.Modello',
            'a.TipoVeicolo',
            'km.KmRiferimento',
            'a.KmTotali',
            'a.TipoCarburante',
            'a.DataUltimaAutorizzazioneSanitaria',
            'a.DataUltimoCollaudo',
        ])
            ->get()
            ->map(function ($row) {
                $row->Azioni = view('partials.actions_automezzo', ['id' => $row->idAutomezzo])->render();
                return $row;
            });
    }
}

<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Automezzo {
    protected const TABLE = 'automezzi';

    public static function getAll(?int $anno = null): Collection
    {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table('automezzi as a')
            ->join('associazioni as s', 'a.idAssociazione', '=', 's.idAssociazione')
            ->join('anni as y', 'a.idAnno', '=', 'y.idAnno')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('a.idAutomezzo', '=', 'km.idAutomezzo')
                    ->where('km.idAnno', '=', $anno);
            })
            ->leftJoin('vehicle_types as vt', 'a.idTipoVeicolo', '=', 'vt.id')
            ->leftJoin('fuel_types as ft', 'a.idTipoCarburante', '=', 'ft.id')
            ->select([
                'a.idAutomezzo',
                'a.idAssociazione',
                's.Associazione',
                'y.anno',
                'a.Automezzo',
                'a.Targa',
                'a.CodiceIdentificativo',
                'a.AnnoPrimaImmatricolazione',
                'a.AnnoAcquisto',
                'a.Modello',
                'vt.nome as TipoVeicolo',
                'km.KmRiferimento',
                'a.KmTotali',
                'ft.nome as TipoCarburante',
                'a.incluso_riparto',
                'a.DataUltimaAutorizzazioneSanitaria',
                'a.DataUltimoCollaudo',
                'a.created_at',
            ])
            ->where('a.idAnno', $anno)
            ->orderBy('s.Associazione')
            ->orderBy('a.Automezzo')
            ->get();
    }

    public static function createAutomezzo(array $data): int {
        return DB::table('automezzi')->insertGetId([
            'idAssociazione'                     => $data['idAssociazione'],
            'idAnno'                             => $data['idAnno'],
            'Automezzo'                          => $data['Automezzo'],
            'Targa'                              => $data['Targa'],
            'CodiceIdentificativo'               => $data['CodiceIdentificativo'],
            'AnnoPrimaImmatricolazione'          => $data['AnnoPrimaImmatricolazione'],
            'AnnoAcquisto'                       => $data['AnnoAcquisto'] ?? null,
            'Modello'                            => $data['Modello'],
            'idTipoVeicolo'                      => $data['idTipoVeicolo'],
            'incluso_riparto'                    => $data['incluso_riparto'] ?? 0,
            'KmTotali'                           => $data['KmTotali'],
            'idTipoCarburante'                   => $data['idTipoCarburante'],
            'DataUltimaAutorizzazioneSanitaria'  => $data['DataUltimaAutorizzazioneSanitaria'],
            'DataUltimoCollaudo'                 => $data['DataUltimoCollaudo'],
            'created_at'                         => Carbon::now(),
            'updated_at'                         => Carbon::now(),
        ]);
    }

    public static function getById(int $idAutomezzo, ?int $anno = null) {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table('automezzi as a')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('a.idAutomezzo', '=', 'km.idAutomezzo')
                    ->where('km.idAnno', '=', $anno);
            })
            ->leftJoin('vehicle_types as vt', 'a.idTipoVeicolo', '=', 'vt.id')
            ->leftJoin('fuel_types as ft', 'a.idTipoCarburante', '=', 'ft.id')
            ->where('a.idAutomezzo', $idAutomezzo)
            ->select([
                'a.*',
                'km.KmRiferimento as KmRiferimento',
                'vt.nome as TipoVeicolo',
                'ft.nome as TipoCarburante',
            ])
            ->first();
    }

    public static function updateAutomezzo(int $idAutomezzo, array $data): void {
        DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->update([
                'idAssociazione'                     => $data['idAssociazione'],
                'idAnno'                             => $data['idAnno'],
                'Automezzo'                          => $data['Automezzo'],
                'Targa'                              => $data['Targa'],
                'CodiceIdentificativo'               => $data['CodiceIdentificativo'],
                'AnnoPrimaImmatricolazione'          => $data['AnnoPrimaImmatricolazione'],
                'AnnoAcquisto'                       => $data['AnnoAcquisto'] ?? null,
                'Modello'                            => $data['Modello'],
                'idTipoVeicolo'                      => $data['idTipoVeicolo'],
                'incluso_riparto'                    => $data['incluso_riparto'],
                'KmTotali'                           => $data['KmTotali'],
                'idTipoCarburante'                   => $data['idTipoCarburante'],
                'DataUltimaAutorizzazioneSanitaria'  => $data['DataUltimaAutorizzazioneSanitaria'],
                'DataUltimoCollaudo'                 => $data['DataUltimoCollaudo'],
                'updated_at'                         => Carbon::now(),
            ]);
    }

    public static function deleteAutomezzo(int $idAutomezzo): void {
        AutomezzoKmRiferimento::deleteByAutomezzo($idAutomezzo);
        DB::table('automezzi')->where('idAutomezzo', $idAutomezzo)->delete();
    }

    public static function getByAssociazione(?int $idAssociazione, ?int $anno = null): Collection {
        $anno = $anno ?? session('anno_riferimento', now()->year);

        return DB::table(self::TABLE . ' as a')
            ->join('associazioni as s', 'a.idAssociazione', '=', 's.idAssociazione')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('a.idAutomezzo', '=', 'km.idAutomezzo')
                    ->where('km.idAnno', '=', $anno);
            })
            ->leftJoin('vehicle_types as vt', 'a.idTipoVeicolo', '=', 'vt.id')
            ->leftJoin('fuel_types as ft', 'a.idTipoCarburante', '=', 'ft.id')
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
                'a.incluso_riparto',
                'vt.nome as TipoVeicolo',
                'km.KmRiferimento',
                'a.KmTotali',
                'ft.nome as TipoCarburante',
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
     * Recupera dati per DataTables filtrati per anno e associazione.
     * Il filter $assocId è già determinato dal controller in base ai ruoli.
     *
     * @param int $anno
     * @param int|null $assocId
     * @return Collection
     */
    public static function getForDataTable(int $anno, ?int $assocId): Collection
    {
        $query = DB::table('automezzi as a')
            ->join('associazioni as ass', 'ass.idAssociazione', '=', 'a.idAssociazione')
            ->leftJoin('automezzi_km_riferimento as km', function ($join) use ($anno) {
                $join->on('km.idAutomezzo', '=', 'a.idAutomezzo')
                    ->where('km.idAnno', $anno);
            })
            ->leftJoin('vehicle_types as vt', 'a.idTipoVeicolo', '=', 'vt.id')
            ->leftJoin('fuel_types as ft', 'a.idTipoCarburante', '=', 'ft.id')
            ->where('a.idAnno', $anno);

        if ($assocId) {
            $query->where('a.idAssociazione', $assocId);
        }

        $rows = $query->select([
            'a.idAutomezzo',
            'ass.Associazione',
            'a.idAnno',
            'a.Automezzo',
            'a.Targa',
            'a.CodiceIdentificativo',
            'a.AnnoPrimaImmatricolazione',
            'a.Modello',
            'a.incluso_riparto',
            'vt.nome as TipoVeicolo',
            'km.KmRiferimento',
            'a.KmTotali',
            'ft.nome as TipoCarburante',
            'a.DataUltimaAutorizzazioneSanitaria',
            'a.DataUltimoCollaudo',
        ])
        ->get()
        ->map(function ($row) {
            $row->Azioni = view('partials.actions_automezzo', ['id' => $row->idAutomezzo])->render();
            return $row;
        });

        return $rows;
    }

    public static function getLightForAnno(int $anno, ?int $idAssociazione = null): Collection {
        return DB::table('automezzi')
            ->where('idAnno', operator: $anno)
            ->when($idAssociazione, fn($q) => $q->where('idAssociazione', $idAssociazione))
            ->select('idAutomezzo', 'Automezzo', 'Targa', 'CodiceIdentificativo')
            ->get();
    }

    public static function getForRipartizione(int $anno, ?int $idAssociazione = null): Collection {
        return DB::table('automezzi')
            ->where('idAnno', $anno)
            ->when($idAssociazione, fn($q) => $q->where('idAssociazione', $idAssociazione))
            ->select('idAutomezzo', 'Targa', 'CodiceIdentificativo')
            ->orderBy('Targa')
            ->get();
    }

    public static function getFiltratiByUtente($anno): Collection {
        $user = Auth::user();
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            return self::getAll($anno);
        }

        $idAssoc = $user->IdAssociazione;
        abort_if(!$idAssoc, 403, "Associazione non trovata per l'utente.");
        return self::getByAssociazione($idAssoc, $anno);
    }
}

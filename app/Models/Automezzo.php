<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Automezzo {
    protected const TABLE = 'automezzi';

    /* ----------------------------------------
     * Helpers
     * -------------------------------------- */
    private static function toIntOrNull($v): ?int {
        if ($v === null || $v === '') return null;
        // normalizza eventuali stringhe con separatori/virgole
        $n = (int) round((float) str_replace([',', ' '], ['.', ''], (string)$v));
        return $n;
    }

    /* ----------------------------------------
     * READ
     * -------------------------------------- */
    public static function getAll(?int $anno = null): Collection {
        $anno = $anno ?? (int) session('anno_riferimento', now()->year);

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
                'a.Targa',
                'a.CodiceIdentificativo',
                'a.AnnoPrimaImmatricolazione',
                'a.AnnoAcquisto',
                'a.Modello',
                'vt.nome as TipoVeicolo',
                // 👇 Cast a intero in SELECT
                DB::raw('CAST(km.KmRiferimento AS SIGNED) as KmRiferimento'),
                DB::raw('CAST(a.KmTotali      AS SIGNED) as KmTotali'),
                'ft.nome as TipoCarburante',
                'a.incluso_riparto',
                'a.DataUltimaAutorizzazioneSanitaria',
                'a.DataUltimoCollaudo',
                'a.informazioniAggiuntive',
                'a.note',
                'a.created_at',
            ])
            ->where('a.idAnno', $anno)
            ->orderBy('s.Associazione')
            ->orderBy('a.idAutomezzo')
            ->get();
    }

    public static function getById(int $idAutomezzo, ?int $anno = null) {
        $anno = $anno ?? (int) session('anno_riferimento', now()->year);

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
                // 👇 Cast a intero
                DB::raw('CAST(km.KmRiferimento AS SIGNED) as KmRiferimento'),
                'vt.nome as TipoVeicolo',
                'ft.nome as TipoCarburante',
            ])
            ->first();
    }

    public static function getByAssociazione(?int $idAssociazione, ?int $anno = null): Collection {
        $anno = $anno ?? (int) session('anno_riferimento', now()->year);

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
                'a.Targa',
                'a.CodiceIdentificativo',
                'a.AnnoPrimaImmatricolazione',
                'a.AnnoAcquisto',
                'a.Modello',
                'a.incluso_riparto',
                'vt.nome as TipoVeicolo',
                // 👇 Cast a intero
                DB::raw('CAST(km.KmRiferimento AS SIGNED) as KmRiferimento'),
                DB::raw('CAST(a.KmTotali      AS SIGNED) as KmTotali'),
                'ft.nome as TipoCarburante',
                'a.DataUltimaAutorizzazioneSanitaria',
                'a.DataUltimoCollaudo',
                'a.note',
                'a.informazioniAggiuntive',
            ])
            ->where('a.idAssociazione', $idAssociazione)
            ->where('a.idAnno', $anno)
            ->orderBy('a.idAnno', 'desc')
            ->orderBy('a.idAutomezzo')
            ->get();
    }

    public static function getByAssociazioneInclusoRiparto(?int $idAssociazione, ?int $anno = null): Collection {
        $anno = $anno ?? (int) session('anno_riferimento', now()->year);

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
                'a.Targa',
                'a.CodiceIdentificativo',
                'a.AnnoPrimaImmatricolazione',
                'a.AnnoAcquisto',
                'a.Modello',
                'a.incluso_riparto',
                'vt.nome as TipoVeicolo',
                // 👇 Cast a intero
                DB::raw('CAST(km.KmRiferimento AS SIGNED) as KmRiferimento'),
                DB::raw('CAST(a.KmTotali      AS SIGNED) as KmTotali'),
                'ft.nome as TipoCarburante',
                'a.DataUltimaAutorizzazioneSanitaria',
                'a.DataUltimoCollaudo',
                'a.note',
                'a.informazioniAggiuntive',
            ])
            ->where('a.idAssociazione', $idAssociazione)
            ->where('a.idAnno', $anno)
            ->where('a.incluso_riparto', '=', 1)
            ->orderBy('a.idAnno', 'desc')
            ->orderBy('a.idAutomezzo')
            ->get();
    }

    public static function getForDataTable(int $anno, ?int $assocId): Collection {
        // KM ESERCIZIO = somma KMPercorsi per automezzo nell'anno (anno preso da automezzi.idAnno)
        $kmEsercizioSub = DB::table('automezzi_km as k')
            ->join('automezzi as a2', 'a2.idAutomezzo', '=', 'k.idAutomezzo')
            ->where('a2.idAnno', $anno)
            ->when($assocId, function ($q) use ($assocId) {
                $q->where('a2.idAssociazione', $assocId);
            })
            ->groupBy('k.idAutomezzo')
            ->selectRaw('k.idAutomezzo, SUM(COALESCE(k.KMPercorsi,0)) as KmEsercizio');

        $query = DB::table('automezzi as a')
            ->join('associazioni as ass', 'ass.idAssociazione', '=', 'a.idAssociazione')
            ->leftJoinSub($kmEsercizioSub, 'kme', function ($join) {
                $join->on('kme.idAutomezzo', '=', 'a.idAutomezzo');
            })
            ->leftJoin('vehicle_types as vt', 'a.idTipoVeicolo', '=', 'vt.id')
            ->leftJoin('fuel_types as ft', 'a.idTipoCarburante', '=', 'ft.id')
            ->where('a.idAnno', $anno);

        if ($assocId) {
            $query->where('a.idAssociazione', $assocId);
        }

        return $query->select([
            'a.idAutomezzo',
            'ass.Associazione',
            'a.idAnno',
            'a.Targa',
            'a.CodiceIdentificativo',
            'a.AnnoPrimaImmatricolazione',
            'a.AnnoAcquisto',
            'a.Modello',
            'a.incluso_riparto',
            'vt.nome as TipoVeicolo',

            // se KMPercorsi è decimal e vuoi intero “da tabella”, arrotonda:
            DB::raw('CAST(ROUND(COALESCE(kme.KmEsercizio,0),0) AS SIGNED) as KmEsercizio'),

            DB::raw('CAST(a.KmTotali AS SIGNED) as KmTotali'),

            'ft.nome as TipoCarburante',
            'a.DataUltimaAutorizzazioneSanitaria',
            'a.DataUltimoCollaudo',
            'a.note',
            'a.informazioniAggiuntive',
        ])
            ->get()
            ->map(function ($row) {
                $row->Azioni = view('partials.actions_automezzo', ['id' => $row->idAutomezzo])->render();
                return $row;
            });
    }




    public static function getLightForAnno(int $anno, ?int $idAssociazione = null): Collection {
        return DB::table('automezzi')
            ->where('idAnno', $anno) // ✅ fix del refuso
            ->when($idAssociazione, function ($q) use ($idAssociazione) {
                $q->where('idAssociazione', $idAssociazione);
            })
            ->select('idAutomezzo', 'Targa', 'CodiceIdentificativo', 'note')
            ->get();
    }

    public static function getForRipartizione(int $anno, ?int $idAssociazione = null): Collection {
        return DB::table('automezzi')
            ->where('idAnno', $anno)
            ->when($idAssociazione, function ($q) use ($idAssociazione) {
                $q->where('idAssociazione', $idAssociazione);
            })
            ->select('idAutomezzo', 'Targa', 'CodiceIdentificativo', 'note')
            ->orderBy('Targa')
            ->get();
    }

    /* ----------------------------------------
     * WRITE
     * -------------------------------------- */
    public static function createAutomezzo(array $data): int {
        return DB::table('automezzi')->insertGetId([
            'idAssociazione'                     => (int) $data['idAssociazione'],
            'idAnno'                             => (int) $data['idAnno'],
            'Targa'                              => $data['Targa'],
            'CodiceIdentificativo'               => $data['CodiceIdentificativo'],
            'AnnoPrimaImmatricolazione'          => Automezzo::toIntOrNull($data['AnnoPrimaImmatricolazione'] ?? null),
            'AnnoAcquisto'                       => Automezzo::toIntOrNull($data['AnnoAcquisto'] ?? null),
            'Modello'                            => $data['Modello'],
            'idTipoVeicolo'                      => isset($data['idTipoVeicolo']) && $data['idTipoVeicolo'] !== null
                ? (int)$data['idTipoVeicolo']
                : 1,
            'incluso_riparto'                    => (int) ($data['incluso_riparto'] ?? 0),
            'KmTotali'                           => self::toIntOrNull($data['KmTotali'] ?? null),
            'idTipoCarburante'                   => isset($data['idTipoCarburante']) ? (int)$data['idTipoCarburante'] : 1,
            'DataUltimaAutorizzazioneSanitaria'  => $data['DataUltimaAutorizzazioneSanitaria'],
            'DataUltimoCollaudo'                 => $data['DataUltimoCollaudo'],
            'note'                               => $data['note'] ?? null,
            'informazioniAggiuntive'             => $data['informazioniAggiuntive'] ?? null,
            'created_at'                         => Carbon::now(),
            'updated_at'                         => Carbon::now(),
        ]);
    }

    public static function updateAutomezzo(int $idAutomezzo, array $data): void {
        DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->update([
                'idAssociazione'                     => (int) $data['idAssociazione'],
                'idAnno'                             => (int) $data['idAnno'],
                'Targa'                              => $data['Targa'],
                'CodiceIdentificativo'               => $data['CodiceIdentificativo'],
                'AnnoPrimaImmatricolazione'          => (int) $data['AnnoPrimaImmatricolazione'],
                'AnnoAcquisto'                       => isset($data['AnnoAcquisto']) ? (int) $data['AnnoAcquisto'] : null,
                'Modello'                            => $data['Modello'],
                'idTipoVeicolo'                      => isset($data['idTipoVeicolo']) && $data['idTipoVeicolo'] !== null
                    ? (int)$data['idTipoVeicolo']
                    : 1,
                'incluso_riparto'                    => (int) ($data['incluso_riparto'] ?? 0),
                'KmTotali'                           => self::toIntOrNull($data['KmTotali'] ?? null),
                'idTipoCarburante'                   => (int) $data['idTipoCarburante'] ?? 1,
                'DataUltimaAutorizzazioneSanitaria'  => $data['DataUltimaAutorizzazioneSanitaria'],
                'DataUltimoCollaudo'                 => $data['DataUltimoCollaudo'],
                'note'                               => $data['note'] ?? null,
                'informazioniAggiuntive'             => $data['informazioniAggiuntive'] ?? null,
                'updated_at'                         => Carbon::now(),
            ]);
    }

    public static function deleteAutomezzo(int $idAutomezzo, ?int $anno = null): void {
        // 1) figli diretti gestiti a codice
        AutomezzoKmRiferimento::deleteByAutomezzo($idAutomezzo, $anno);

        // automezzi_km (se esiste e magari ha idAnno)
        if (Schema::hasTable('automezzi_km')) {
            $q = DB::table('automezzi_km')->where('idAutomezzo', $idAutomezzo);
            if ($anno && Schema::hasColumn('automezzi_km', 'idAnno')) $q->where('idAnno', $anno);
            $q->delete();
        }

        // TODO: qui elimina eventuali altre tabelle figlie che referenziano idAutomezzo
        // es.: ripartizioni, costi_automezzi, servizi_svolti, ecc.

        // 2) infine il padre
        DB::table('automezzi')->where('idAutomezzo', $idAutomezzo)->delete();
    }

    /* ----------------------------------------
     * Utility aggiornamento KM totali
     * -------------------------------------- */
    public static function refreshKmTotaliFor(int $idAutomezzo): void {
        $sum = (int) DB::table('automezzi_km')
            ->where('idAutomezzo', $idAutomezzo)
            ->sum(DB::raw('COALESCE(KMPercorsi,0)'));

        DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->update([
                'KmTotali'   => $sum,
                'updated_at' => now(),
            ]);
    }


    /* ----------------------------------------
     * Filtri per ruolo
     * -------------------------------------- */
    public static function getFiltratiByUtente($anno): Collection {
        $user = Auth::user();
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            return self::getAll($anno);
        }

        $idAssoc = $user->IdAssociazione;
        abort_if(!$idAssoc, 403, "Associazione non trovata per l'utente.");
        return self::getByAssociazione($idAssoc, $anno);
    }

    public static function calcKmTotaliByIdentity(int $idAssociazione, string $targa, string $codiceIdentificativo): int {
        return (int) DB::table('automezzi_km as k')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 'k.idAutomezzo')
            ->where('a.idAssociazione', $idAssociazione)
            ->where('a.Targa', $targa)
            ->where('a.CodiceIdentificativo', $codiceIdentificativo)
            ->sum(DB::raw('COALESCE(k.KMPercorsi,0)'));
    }

    public static function calcKmEsercizioByIdentity(int $idAssociazione, string $targa, string $codiceIdentificativo, int $anno): int {
        return (int) DB::table('automezzi_km as k')
            ->join('automezzi as a', 'a.idAutomezzo', '=', 'k.idAutomezzo')
            ->join('convenzioni as c', 'c.idConvenzione', '=', 'k.idConvenzione')
            ->where('a.idAssociazione', $idAssociazione)
            ->where('a.Targa', $targa)
            ->where('a.CodiceIdentificativo', $codiceIdentificativo)
            ->where('c.idAnno', $anno)
            ->sum(DB::raw('COALESCE(k.KMPercorsi,0)'));
    }
}

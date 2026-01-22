<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;
use Illuminate\Support\Collection;

class RipartizioneOssigeno {
    private const RIPARTO_SI = 'SI';
    private const RIPARTO_NO = 'NO';
    private const TABLE_AUTOMEZZI = 'automezzi';

    public static function getRipartizione(?int $idAssociazione, int $anno): array {
        // 1) Automezzi inclusi
        $automezziQuery = DB::table(self::TABLE_AUTOMEZZI)
            ->where('idAnno', $anno);

        if (!is_null($idAssociazione)) {
            $automezziQuery->where('idAssociazione', $idAssociazione);
        }

        $automezzi = $automezziQuery
            ->select('idAutomezzo', 'Targa', 'CodiceIdentificativo', 'incluso_riparto')
            ->where('incluso_riparto', 1)
            ->get()
            ->keyBy('idAutomezzo');

        if ($automezzi->isEmpty()) {
            return [
                'convenzioni'    => collect(),
                'righe'          => [],
                'totale_inclusi' => 0,
            ];
        }

        // 2) Convenzioni
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);

        if ($convenzioni->isEmpty()) {
            return [
                'convenzioni'    => $convenzioni,
                'righe'          => [],
                'totale_inclusi' => 0,
            ];
        }

        $convIds = $convenzioni->pluck('idConvenzione')->map(fn($v) => (int)$v)->all();

        // 2b) Mappa convId => true se materiale fornito ASL (=> ESCLUDI servizi)
        $convAslMap = DB::table('convenzioni')
            ->where('idAnno', $anno)
            ->when(!is_null($idAssociazione), function ($q) use ($idAssociazione) {
                $q->where('idAssociazione', $idAssociazione);
            })
            ->whereIn('idConvenzione', $convIds)
            ->pluck('materiale_fornito_asl', 'idConvenzione')
            ->map(fn($v) => ((int)$v === 1))
            ->toArray();

        // 3) Servizi per (automezzo, convenzione)
        $servizi = DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $automezzi->keys())
            ->whereIn('idConvenzione', $convIds)
            ->select('idAutomezzo', 'idConvenzione', 'NumeroServizi')
            ->get();

        $serviziIndicizzati = $servizi->keyBy(fn($s) => $s->idAutomezzo . '-' . $s->idConvenzione);

        // 4) Righe
        $righe                = [];
        $totaleInclusi        = 0;
        $totaliPerConvenzione = [];

        foreach ($automezzi as $id => $auto) {
            $incluso = filter_var($auto->incluso_riparto, FILTER_VALIDATE_BOOLEAN);

            $riga = [
                'idAutomezzo'         => (int)$id,
                'Targa'               => $auto->Targa,
                'CodiceIdentificativo' => $auto->CodiceIdentificativo,
                'incluso_riparto'     => $incluso,
                'valori'              => [],
                'totale'              => 0,
            ];

            foreach ($convenzioni as $conv) {
                $cid = (int)$conv->idConvenzione;

                // ESCLUSIONE: materiale fornito ASL => servizi = 0
                if (!empty($convAslMap[$cid])) {
                    $num = 0;
                } else {
                    $key = $id . '-' . $cid;
                    $num = isset($serviziIndicizzati[$key]) ? (int)$serviziIndicizzati[$key]->NumeroServizi : 0;
                }

                $riga['valori'][$cid] = $num;
                $riga['totale']      += $num;

                if (!isset($totaliPerConvenzione[$cid])) {
                    $totaliPerConvenzione[$cid] = 0;
                }
                $totaliPerConvenzione[$cid] += $incluso ? $num : 0;
            }

            if ($incluso) {
                $totaleInclusi += $riga['totale'];
            }

            $righe[(int)$id] = $riga;
        }

        // 5) Riga totale
        $rigaTotale = [
            'idAutomezzo'      => null,
            'Targa'            => 'TOTALE',
            'CodiceIdentificativo' => '',
            'incluso_riparto'  => true,
            'valori'           => [],
            'totale'           => $totaleInclusi,
            'is_totale'        => true,
        ];

        foreach ($convenzioni as $conv) {
            $cid = (int)$conv->idConvenzione;
            $rigaTotale['valori'][$cid] = $totaliPerConvenzione[$cid] ?? 0;
        }

        $righe['totale'] = $rigaTotale;

        return [
            'convenzioni'    => $convenzioni,
            'righe'          => $righe,
            'totale_inclusi' => $totaleInclusi,
        ];
    }

    public static function getTotaleServizi(Collection $automezzi, int $anno): int {
        if ($automezzi->isEmpty()) return 0;

        $idAutomezziInclusi = $automezzi
            ->filter(fn($a) => filter_var($a->incluso_riparto, FILTER_VALIDATE_BOOLEAN))
            ->pluck('idAutomezzo');

        if ($idAutomezziInclusi->isEmpty()) return 0;

        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);
        if ($convenzioni->isEmpty()) return 0;

        $convIds = $convenzioni->pluck('idConvenzione')->map(fn($v) => (int)$v)->all();

        // escludi convenzioni ASL
        $convIdsValidi = DB::table('convenzioni')
            ->where('idAnno', $anno)
            ->when(!is_null($idAssociazione), function ($q) use ($idAssociazione) {
                $q->where('idAssociazione', $idAssociazione);
            })
            ->whereIn('idConvenzione', $convIds)
            ->where(function ($q) {
                $q->whereNull('materiale_fornito_asl')
                    ->orWhere('materiale_fornito_asl', 0);
            })
            ->pluck('idConvenzione')
            ->map(fn($v) => (int)$v);

        if ($convIdsValidi->isEmpty()) return 0;

        return (int) DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $idAutomezziInclusi)
            ->whereIn('idConvenzione', $convIdsValidi)
            ->sum('NumeroServizi');
    }
}

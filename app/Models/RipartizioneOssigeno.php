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
        $automezziQuery = DB::table(table: self::TABLE_AUTOMEZZI)
            ->where('idAnno',  $anno);

        if (!is_null($idAssociazione)) {
            $automezziQuery->where('idAssociazione', $idAssociazione);
        }

        $automezzi = $automezziQuery
            ->select('idAutomezzo', 'Targa', 'CodiceIdentificativo', 'incluso_riparto')
            ->where('incluso_riparto',1)
            ->get()
            ->keyBy('idAutomezzo');

        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);

        $servizi = DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $automezzi->keys())
            ->whereIn('idConvenzione', $convenzioni->pluck('idConvenzione'))
            ->select('idAutomezzo', 'idConvenzione', 'NumeroServizi')
            ->get();

        $serviziIndicizzati = $servizi->keyBy(fn($s) => $s->idAutomezzo . '-' . $s->idConvenzione);

        $righe = [];
        $totaleInclusi = 0;
        $totaliPerConvenzione = [];

        foreach ($automezzi as $id => $auto) {
            $incluso = filter_var($auto->incluso_riparto, FILTER_VALIDATE_BOOLEAN);

            $riga = [
                'idAutomezzo' => $id,
                'Targa' => $auto->Targa,
                'CodiceIdentificativo' => $auto->CodiceIdentificativo,
                'incluso_riparto' => $incluso,
                'valori' => [],
                'totale' => 0
            ];

            foreach ($convenzioni as $conv) {
                $key = $id . '-' . $conv->idConvenzione;
                $num = isset($serviziIndicizzati[$key]) ? (int) $serviziIndicizzati[$key]->NumeroServizi : 0;
                $riga['valori'][$conv->idConvenzione] = $num;
                $riga['totale'] += $num;

                if (!isset($totaliPerConvenzione[$conv->idConvenzione])) {
                    $totaliPerConvenzione[$conv->idConvenzione] = 0;
                }
                $totaliPerConvenzione[$conv->idConvenzione] += $incluso ? $num : 0;
            }

            if ($incluso) {
                $totaleInclusi += $riga['totale'];
            }

            $righe[$id] = $riga;
        }

        $rigaTotale = [
            'idAutomezzo' => null,
            'Targa' => 'TOTALE',
            'CodiceIdentificativo' => '',
            'incluso_riparto' => true,
            'valori' => [],
            'totale' => $totaleInclusi,
            'is_totale' => true,
        ];

        foreach ($convenzioni as $conv) {
            $rigaTotale['valori'][$conv->idConvenzione] = $totaliPerConvenzione[$conv->idConvenzione] ?? 0;
        }

        $righe['totale'] = $rigaTotale;

        return [
            'convenzioni' => $convenzioni,
            'righe' => $righe,
            'totale_inclusi' => $totaleInclusi
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
        $idConvenzioni = $convenzioni->pluck('idConvenzione');

        if ($idConvenzioni->isEmpty()) return 0;

        return DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $idAutomezziInclusi)
            ->whereIn('idConvenzione', $idConvenzioni)
            ->sum('NumeroServizi');
    }
}

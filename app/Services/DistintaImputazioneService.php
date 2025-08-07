<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DistintaImputazioneService {
    public static function generaTabella(int $idAssociazione, int $anno, int $idSezione): array {
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        $righeIndirette = RipartizioneCostiService::calcolaTabellaTotale($idAssociazione, $anno);
        $voci = self::getVociBySezione($idSezione);

        $tabella = [];

        foreach ($voci as $voce) {
            $riga = [
                'voce'     => $voce,
                'bilancio' => 0,
                'ripartiti' => 0,
                'diretta' => 0,
            ];

            foreach ($convenzioni as $conv) {
                $val = self::estraiValore($righeIndirette, $voce, $conv);
                $riga["indiretti_$conv"] = $val;
                $riga['ripartiti'] += $val;
            }

            $riga['bilancio'] = self::estraiValore($righeIndirette, $voce, 'totale');
            $riga['diretta'] = $riga['bilancio'] - $riga['ripartiti'];

            $tabella[] = $riga;
        }

        return [
            'righe' => $tabella,
            'convenzioni' => $convenzioni,
        ];
    }

    private static function estraiValore(array $tabella, string $voce, string $col) {
        foreach ($tabella as $riga) {
            if (trim(strtoupper($riga['voce'])) === trim(strtoupper($voce))) {
                return floatval($riga[$col] ?? 0);
            }
        }
        return 0;
    }

    private static function getVociBySezione(int $id): array {
        return match ($id) {
            2 => [ // Automezzi
                'LEASING/ NOLEGGIO AUTOMEZZI',
                'ASSICURAZIONE AUTOMEZZI',
                'MANUTENZIONE ORDINARIA',
                'MANUTENZIONE STRAORDINARIA',
                'PULIZIA E DISINFEZIONE AUTOMEZZI',
                'CARBURANTI',
                'ADDITIVI',
                'INTERESSI PASS. F.TO, LEASING, NOL.',
                'ALTRI COSTI MEZZI',
            ],
            3 => [
                'MANUTENZIONE ATTREZZATURA SANITARIA',
                'LEASING ATTREZZATURA SANITARIA',
            ],
            4 => [
                'TELECOMUNICAZIONI',
            ],
            default => []
        };
    }

    public static function calcolaTotaliPerSezione(array $righe, int $sezioneId): array {
        $totaleBilancio = 0.0;
        $totaleDiretta  = 0.0;
        $totaleRipartita = 0.0;

        foreach ($righe as $riga) {
            if ((int) $riga['sezione_id'] !== $sezioneId) {
                continue;
            }

            $totaleBilancio  += (float) $riga['bilancio'];
            $totaleDiretta   += (float) $riga['diretta'];
            $totaleRipartita += (float) $riga['totale'];
        }

        return [
            'totale_bilancio'  => round($totaleBilancio, 2),
            'totale_diretta'   => round($totaleDiretta, 2),
            'totale_ripartita' => round($totaleRipartita, 2),
        ];
    }
}

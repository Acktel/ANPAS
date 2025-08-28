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
        $voci = self::getVociBySezione($idSezione, $anno, $idAssociazione);

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

    private static function getVociBySezione(int $idSezione, int $anno, int $idAssociazione): array {
        return DB::table('riepilogo_voci_config')
            ->where('attivo', 1)
            ->where('idTipologiaRiepilogo', $idSezione) // 2..11
            ->orderBy('ordinamento')
            ->orderBy('id')
            ->pluck('descrizione')
            ->map(fn($d) => trim($d))
            ->values()
            ->toArray();
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

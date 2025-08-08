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
        // Voci statiche per certe sezioni
        $statiche = match ($idSezione) {
            2 => [
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
            default => [],
        };

        // Sezione dinamica?
        if (!in_array($idSezione, [5, 6, 7, 8, 9, 10, 11])) {
            return $statiche;
        }

        // mappa tipologie --> sezione
        $tipologiaToSezione = [
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            11 => 11,
        ];

        // Trova tipologie da includere per questa sezione
        $tipologie = array_keys(array_filter($tipologiaToSezione, fn($s) => $s == $idSezione));
        if (empty($tipologie)) return $statiche;

        // Trova idRiepilogo corretto per questa associazione e anno
        $idRiepilogo = DB::table('riepiloghi')
            ->where('idAnno', $anno)
            ->where('idAssociazione', $idAssociazione)
            ->value('idRiepilogo');

        if (!$idRiepilogo) return $statiche;

        // Prendi le descrizioni delle voci da riepilogo_dati
        $vociDb = DB::table('riepilogo_dati')
                    ->where('idRiepilogo', $idRiepilogo)
                    ->whereIn('idTipologiaRiepilogo', $tipologie)
                    ->pluck('descrizione')
                    ->map(fn($d) => trim(strtoupper($d)))
                    ->unique()
                    ->sort()
                    ->values()
                    ->toArray();

        return array_merge($statiche, $vociDb);
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

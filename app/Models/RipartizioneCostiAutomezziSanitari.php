<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;

class RipartizioneCostiAutomezziSanitari {
    public static function calcola(int $idAutomezzo, int $anno): array {
        // Recupera i costi dell'automezzo
        $costi = DB::table('costi_automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->where('idAnno', $anno)
            ->first();

        // Recupera i costi radio dell'associazione legata all'automezzo
        $idAssociazione = DB::table('automezzi')
            ->where('idAutomezzo', $idAutomezzo)
            ->value('idAssociazione');

        $radio = DB::table('costi_radio')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();


        if (!$costi) {
            // Restituisci tutto a zero
            return self::vociVuote();
        }

        $voci = [
            ['voce' => 'ASSICURAZIONI', 'valore' => $costi->Assicurazione],
            ['voce' => 'MANUTENZIONE ORDINARIA', 'valore' => $costi->ManutenzioneOrdinaria],
            ['voce' => 'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI', 'valore' => $costi->ManutenzioneStraordinaria - $costi->RimborsiAssicurazione],
            ['voce' => 'PULIZIA E DISINFEZIONE', 'valore' => $costi->PuliziaDisinfezione],
            ['voce' => 'CARBURANTI AL NETTO RIMBORSI UTF', 'valore' => $costi->Carburanti - $costi->RimborsiUTF],
            ['voce' => 'ADDITIVI', 'valore' => $costi->Additivi],
            ['voce' => 'INTERESSI PASSIVI F.TO, LEASING, NOL.', 'valore' => $costi->InteressiPassivi],
            ['voce' => 'MANUTENZIONE ATTREZZATURA SANITARIA', 'valore' => $costi->ManutenzioneSanitaria],
            ['voce' => 'LEASING ATTREZZATURA SANITARIA', 'valore' => $costi->LeasingSanitaria],
            ['voce' => 'AMMORTAMENTO ATTREZZATURA SANITARIA', 'valore' => $costi->AmmortamentoSanitaria],
            ['voce' => 'MANUTENZIONE APPARATI RADIO', 'valore' => $radio->ManutenzioneApparatiRadio ?? 0],
            ['voce' => 'MONTAGGIO/SMONTAGGIO RADIO 118', 'valore' => $radio->MontaggioSmontaggioRadio118 ?? 0],
            ['voce' => 'LOCAZIONE PONTE RADIO', 'valore' => $radio->LocazionePonteRadio ?? 0],
            ['voce' => 'AMMORTAMENTO IMPIANTI RADIO', 'valore' => $radio->AmmortamentoImpiantiRadio ?? 0],
        ];

        $totale = array_sum(array_column($voci, 'valore'));

        // Ritorna array formattato per DataTable
        $output = array_map(fn($r) => [
            'voce' => $r['voce'],
            'totale' => round(floatval($r['valore']), 2)
        ], $voci);

        $output[] = [
            'voce' => 'TOTALI',
            'totale' => round($totale, 2),
            'is_totale' => true
        ];

        return $output;
    }

    private static function vociVuote(): array {
        $etichette = [
            'ASSICURAZIONI',
            'MANUTENZIONE ORDINARIA',
            'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI',
            'PULIZIA E DISINFEZIONE',
            'CARBURANTI AL NETTO RIMBORSI UTF',
            'ADDITIVI',
            'INTERESSI PASSIVI F.TO, LEASING, NOL.',
            'MANUTENZIONE ATTREZZATURA SANITARIA',
            'LEASING ATTREZZATURA SANITARIA',
            'AMMORTAMENTO ATTREZZATURA SANITARIA',
            'MANUTENZIONE APPARATI RADIO',
            'MONTAGGIO/SMONTAGGIO RADIO 118',
            'LOCAZIONE PONTE RADIO',
            'AMMORTAMENTO IMPIANTI RADIO',
            'TOTALI'
        ];

        return array_map(function ($etichetta) {
            return [
                'voce' => $etichetta,
                'totale' => 0,
                'is_totale' => $etichetta === 'TOTALI'
            ];
        }, $etichette);
    }

    public static function calcolaQuotaSostitutivaPerConvenzione(
        int $idMezzo,
        int $idConvenzione,
        int $anno
    ): float {

        // 1️⃣ Recupero costi annuali del mezzo
        $c = DB::table('costi_automezzi')
            ->where('idAutomezzo', $idMezzo)
            ->where('idAnno', $anno)
            ->first();

        if (!$c) {
            return 0.0;
        }

        // 2️⃣ Recupero KM totali del mezzo
        $kmTot = (float) DB::table('automezzi_km')
            ->where('idAutomezzo', $idMezzo)
            ->sum('KMPercorsi');

        if ($kmTot <= 0) {
            return 0.0;
        }

        // 3️⃣ Recupero KM del mezzo sulla CONVENZIONE
        $kmConv = (float) DB::table('automezzi_km')
            ->where('idAutomezzo', $idMezzo)
            ->where('idConvenzione', $idConvenzione)
            ->sum('KMPercorsi');

        if ($kmConv <= 0) {
            return 0.0;
        }

        // 4️⃣ Costi annuali - SOLO le voci ammesse ANPAS
        $costo_annuo =
            (float) $c->LeasingNoleggio
            + (float) $c->Assicurazione
            + (float) $c->ManutenzioneOrdinaria
            + ((float)$c->ManutenzioneStraordinaria - (float)$c->RimborsiAssicurazione)
            + (float) $c->PuliziaDisinfezione
            + (float) $c->InteressiPassivi
            + (float) $c->ManutenzioneSanitaria
            + (float) $c->LeasingSanitaria
            + (float) $c->AmmortamentoMezzi
            + (float) $c->AmmortamentoSanitaria
            + (float) $c->AltriCostiMezzi;

        // 5️⃣ Quota proporzionale da imputare alla convenzione
        $quota = $costo_annuo * ($kmConv / $kmTot);

        return round($quota, 2);
    }
}

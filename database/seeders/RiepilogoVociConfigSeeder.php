<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RiepilogoVociConfigSeeder extends Seeder {
    public function run(): void {
        $now = Carbon::now();

        // ===========================
        // Definizione voci per tipologia
        // ===========================
        $byTip = [
            1 => [
                "n. volontari totali iscritti all'associazione come da registro",
                "totale ore effettuate dai volontari per la convenzione",
                "n. volontari servizio civile naz.le in servizio per la convenzione",
                "n. dipendenti dell'associazione come da libro unico al 31/12",
                "n. dipendenti autisti/barellieri in servizio per la convenzione",
                "n. ore contrattuali dipendenti autisti/barellieri (caduno)",
                "n. ore svolte dai dipendenti autisti/barellieri per la convenzione",
                "n. turni notturni svolti dai dipendenti autisti/barellieri per la convenzione (stima)",
                "n. turni festivi svolti dai dipendenti autisti/barellieri per la convenzione (stima)",
                "n. ore straordinario svolte dai dipendenti autisti/barellieri per la convenzione (stima)",
                "numero dipendenti coordinatori tecnici in servizio per l'associazione",
                "n. ore contrattuali dipendenti coordinatori tecnici (stima)",
                "n. ore ripartite per i dipendenti coordinatori tecnici per la convenzione (stima)",
                "numero dipendenti addetti alla logistica in servizio per l'associazione",
                "n. ore contrattuali dipendenti addetti alla logistica (stima)",
                "n. ore ripartite per i dipendenti addetti alla logistica per la convenzione (stima)",
                "numero impiegati amministrativi in servizio per l'associazione",
                "n. ore contrattuali dipendenti impiegati amministrativi (stima)",
                "n. ore ripartite per i dipendenti impiegati amministrativi per la convenzione (stima)",
                "numero dipendenti coordinatori amministrativi in servizio per l'associazione",
                "n. ore contrattuali dipendenti coordinatori amministrativi in servizio (stima)",
                "n. ore ripartite per i dipendenti coordinatori amministrativi per la convenzione (stima)",
                "totale km. percorsi nell'anno dall'associazione",
                "totale km. percorsi nell'anno per la convenzione",
                "totale servizi svolti nell'anno dall'associazione",
                "totale servizi svolti nell'anno per la convenzione",
                "targa mezzo dedicato",
                "mq. locali sede associazione",
                "mq. locali sede dedicati alla postazione (se presso sede), (da valorizzare solo nel caso in cui vi siano locali dedicati esclusivamente alla convenzione)",
                "mq. locali ricovero mezzi e magazzini",
                "mq. locali ricovero mezzi e magazzini dedicati alla postazione, (da valorizzare solo nel caso in cui vi siano locali dedicati esclusivamente alla convenzione)",
            ],

            // 2 — automezzi ed attrezzature sanitarie (vedi tabelle)
            2 => [
                'leasing/ noleggio automezzi',
                'assicurazione automezzi',
                'manutenzione ordinaria',
                'manutenzione straordinaria',
                'pulizia e disinfezione automezzi',
                'carburanti',
                'additivi',
                'interessi pass. f.to, leasing, nol.',
                'altri costi mezzi',
            ],

            // 3 — attrezzatura sanitaria
            3 => [
                'manutenzione attrezzatura sanitaria',
                'leasing attrezzatura sanitaria',
            ],

            // 4 — telecomunicazioni
            4 => [
                'manutenzione apparati radio',
                'montaggio/smontaggio radio 118',
                'canoni locazione ponte radio',
            ],

            // 5 — costi di gestione della struttura
            5 => [
                'locazione sede',
                'riscaldamento sede',
                'pulizia e disinfezione sede',
                'spese condominiali',
                'utenze (altre generiche)',
                'manutenzione ordinaria sede',
                'assicurazione sede',
                'imposte e tasse sede',
                'erogazione gas',
                'costi telefonia fissa',
                'costi telefonia mobile',
                'energia elettrica',
                'acqua',
            ],

            // 6 — costo del personale
            6 => [
                'retribuzioni, oneri, fondo tfr e consulenze esterne autisti e barellieri',
                'retribuzioni, oneri, fondo tfr e consulenze esterne coordinatori tecnici',
                'retribuzioni, oneri, fondo tfr e consulenze esterne personale addetto pulizia e disinfezione sede',
                'retribuzioni, oneri, fondo tfr e consulenze esterne personale addetti alla logistica',
                'retribuzioni, oneri, fondo tfr e consulenze esterne personale amministrativo',
                'retribuzioni, oneri, fondo tfr e consulenze esterne coordinatori amministrativi',

                // volontari
                'Volontari: rimborso spese pasti',
                'Volontari: rimborsi da avvicendamenti',
                'Volontari: assicurazioni volontari',
                'Volontari: formazione allegato a + dae',
                'Volontari: formazione rdae',
                'Volontari: formazione trasporto infermi (sara)',

                // servizio civile nazionale              
                'Servizio Civile Nazionale:quota anpas servizio civile nazionale',

                // abbigliamento
                'Abbigliamento: divise personale associazione',
            ],

            // 7 — materiale sanitario di consumo
            7 => [
                'materiale sanitario di consumo',
                'ossigeno'
            ],

            // 8 — costi amministrativi
            8 => [
                'spese postali',
                'imposte e tasse',
                'sconti ed abbuoni passivi',
                'cancelleria',
                'canoni manutenzione vari',
                'emolumenti revisori dei conti',
                'consulenze (specificare)',
            ],

            // 9 — quote di ammortamento
            9 => [
                'automezzi',
                'arredamenti',
                'macchine ufficio',
                'impianti radio',
                'attrezzature ambulanze',
                'hardware',
                'software',
                'fabbricati e capannoni',
                'costi pluriennali ristrutturazione sede',
            ],

            // 10 — beni strumentali inferiori i 516,00
            10 => [
                'beni strumentali inferiori i 516,00',
            ],

            // 11 — altri costi
            11 => [
                'oneri bancari',
                'altri costi',
            ],
        ];

        // ===========================
        // Prelievo ID esistenti per descrizione (così l’ID resta uguale anche se cambia tipologia)
        // ===========================
        $allDescriptions = [];
        foreach ($byTip as $tip => $list) {
            foreach ($list as $d) $allDescriptions[] = $d;
        }

        $existing = DB::table('riepilogo_voci_config')
            ->whereIn('descrizione', $allDescriptions)
            ->pluck('id', 'descrizione'); // ['descrizione' => id]

        // ===========================
        // Costruzione righe con ID stabili (tip*1000 + progressivo)
        // ===========================
        $rows = [];
        $usedIds = DB::table('riepilogo_voci_config')->pluck('id')->all();
        $usedIds = array_flip($usedIds);

        foreach ($byTip as $tipologia => $list) {
            $ord = 1;
            foreach ($list as $i => $descrizione) {
                $id = $existing[$descrizione] ?? null;
                if (!$id) {
                    $candidate = $tipologia * 1000 + ($i + 1);
                    while (isset($usedIds[$candidate])) $candidate++;
                    $id = $candidate;
                    $usedIds[$id] = true;
                }

                $rows[] = [
                    'id'                   => (int)$id,
                    'idTipologiaRiepilogo' => (int)$tipologia,
                    'descrizione'          => $descrizione,
                    'ordinamento'          => $ord++,
                    'attivo'               => 1,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }

        // ===========================
        // Upsert per ID
        // ===========================
        DB::table('riepilogo_voci_config')->upsert(
            $rows,
            ['id'],
            ['idTipologiaRiepilogo', 'descrizione', 'ordinamento', 'attivo', 'updated_at']
        );
    }
}

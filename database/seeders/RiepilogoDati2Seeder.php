<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RiepilogoDati2Seeder extends Seeder
{
    public function run(): void
    {
        $dati = [

            // Tipologia 2 - Automezzi
            ['idTipologiaRiepilogo' => 2, 'descrizione' => 'Leasing', 'preventivo' => 0.00, 'consuntivo' => 1783.47],
            ['idTipologiaRiepilogo' => 2, 'descrizione' => 'Assicurazione', 'preventivo' => 1500.00, 'consuntivo' => 9158.69],
            ['idTipologiaRiepilogo' => 2, 'descrizione' => 'Pulizia e disinfezione', 'preventivo' => 6500.00, 'consuntivo' => 7504.89],
            ['idTipologiaRiepilogo' => 2, 'descrizione' => 'Carburante', 'preventivo' => 0.00, 'consuntivo' => 219.57],
            ['idTipologiaRiepilogo' => 2, 'descrizione' => 'Interessi finanziamento', 'preventivo' => 80.00, 'consuntivo' => 17.25],

            // Tipologia 3 - Attrezzatura Sanitaria
            ['idTipologiaRiepilogo' => 3, 'descrizione' => 'Manutenzione attrezzature sanitarie', 'preventivo' => 1500.00, 'consuntivo' => 2199.46],

            // Tipologia 5 - Costi gestione struttura
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Locazione', 'preventivo' => 1000.00, 'consuntivo' => 973.73],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Riscaldamento', 'preventivo' => 0.00, 'consuntivo' => 6252.61],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Pulizia sede', 'preventivo' => 4800.00, 'consuntivo' => 0.00],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Utenze generiche', 'preventivo' => 1500.00, 'consuntivo' => 2386.67],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Manutenzione ordinaria sede', 'preventivo' => 1100.00, 'consuntivo' => 795.99],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Assicurazione sede', 'preventivo' => 400.00, 'consuntivo' => 812.81],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Utenze telefoniche', 'preventivo' => 1500.00, 'consuntivo' => 1409.53],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Energia elettrica', 'preventivo' => 6800.00, 'consuntivo' => 4876.10],
            ['idTipologiaRiepilogo' => 5, 'descrizione' => 'Acqua', 'preventivo' => 0.00, 'consuntivo' => 553.63],

            // Tipologia 6 - Costo del personale
            ['idTipologiaRiepilogo' => 6, 'descrizione' => 'Autisti e barellieri', 'preventivo' => 142000.00, 'consuntivo' => 141740.65],
            ['idTipologiaRiepilogo' => 6, 'descrizione' => 'Coordinatori tecnici', 'preventivo' => 2500.00, 'consuntivo' => 3669.25],
            ['idTipologiaRiepilogo' => 6, 'descrizione' => 'Volontari - pasti', 'preventivo' => 2912.00, 'consuntivo' => 2912.00],
            ['idTipologiaRiepilogo' => 6, 'descrizione' => 'Volontari - rimborso spese', 'preventivo' => 3000.00, 'consuntivo' => 3252.17],
            ['idTipologiaRiepilogo' => 6, 'descrizione' => 'Volontari - assicurazioni', 'preventivo' => 2000.00, 'consuntivo' => 1776.55],
            ['idTipologiaRiepilogo' => 6, 'descrizione' => 'Formazione SARA', 'preventivo' => 2000.00, 'consuntivo' => 1339.29],
            ['idTipologiaRiepilogo' => 6, 'descrizione' => 'Spese personale', 'preventivo' => 8052.00, 'consuntivo' => 8052.00],

            // Tipologia 7 - Materiale sanitario
            ['idTipologiaRiepilogo' => 7, 'descrizione' => 'Materiale sanitario di consumo', 'preventivo' => 1500.00, 'consuntivo' => 2062.23],

            // Tipologia 8 - Costi amministrativi
            ['idTipologiaRiepilogo' => 8, 'descrizione' => 'Spese postali', 'preventivo' => 150.00, 'consuntivo' => 147.94],
            ['idTipologiaRiepilogo' => 8, 'descrizione' => 'Imposte e tasse', 'preventivo' => 100.00, 'consuntivo' => 6.23],
            ['idTipologiaRiepilogo' => 8, 'descrizione' => 'Sconti ed abbuoni passivi', 'preventivo' => 1500.00, 'consuntivo' => 1916.98],
            ['idTipologiaRiepilogo' => 8, 'descrizione' => 'Cancelleria', 'preventivo' => 500.00, 'consuntivo' => 968.23],
            ['idTipologiaRiepilogo' => 8, 'descrizione' => 'Onorari manutenzione vari', 'preventivo' => 2000.00, 'consuntivo' => 1831.98],
            ['idTipologiaRiepilogo' => 8, 'descrizione' => 'Consulenze', 'preventivo' => 2000.00, 'consuntivo' => 2000.00],

            // Tipologia 9 - Quote di ammortamento
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Automezzi', 'preventivo' => 0.00, 'consuntivo' => 90.49],
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Macchine d\'ufficio', 'preventivo' => 200.00, 'consuntivo' => 0.00],
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Impianti radio', 'preventivo' => 50.00, 'consuntivo' => 40.18],
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Attrezzature ambulanze', 'preventivo' => 200.00, 'consuntivo' => 85.25],
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Hardware', 'preventivo' => 0.00, 'consuntivo' => 6.00],
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Software', 'preventivo' => 0.00, 'consuntivo' => 344.17],
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Fabbricati e capannoni', 'preventivo' => 7000.00, 'consuntivo' => 6980.41],
            ['idTipologiaRiepilogo' => 9, 'descrizione' => 'Ristrutturazione sede', 'preventivo' => 1200.00, 'consuntivo' => 4282.52],

            // Tipologia 10 - Beni Strumentali < 516€
            ['idTipologiaRiepilogo' => 10, 'descrizione' => 'Beni strumentali < 516,00€', 'preventivo' => 350.00, 'consuntivo' => 319.75],

            // Tipologia 11 - Altri costi
            ['idTipologiaRiepilogo' => 11, 'descrizione' => 'Oneri bancari e interessi', 'preventivo' => 500.00, 'consuntivo' => 546.78],
        ];

        foreach ($dati as $voce) {
            DB::table('riepilogo_dati')->insert([
                'idRiepilogo' => 1,
                'idAnno' => 2024,
                'idTipologiaRiepilogo' => $voce['idTipologiaRiepilogo'],
                'descrizione' => $voce['descrizione'],
                'preventivo' => $voce['preventivo'],
                'consuntivo' => $voce['consuntivo'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

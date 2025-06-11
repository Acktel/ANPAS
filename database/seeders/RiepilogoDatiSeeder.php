<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class RiepilogoDatiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Imposta l'id del riepilogo esistente
        $idRiepilogo = 2;

        $descrizioni = [
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
        ];

        $now = Carbon::now();

        $rows = array_map(function($descr) use ($idRiepilogo, $now) {
            return [
                'idRiepilogo' => $idRiepilogo,
                'descrizione' => $descr,
                'preventivo'  => 0.00,
                'consuntivo'  => 0.00,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }, $descrizioni);

        DB::table('riepilogo_dati')->insert($rows);
    }
}

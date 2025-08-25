<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\CostoMaterialeSanitario;
use App\Models\RipartizioneMaterialeSanitario;
use App\Models\Automezzo;

class RipartizioneCostiService {
    public static function getMaterialiSanitariConsumo(int $idAssociazione, int $idAnno, int $idAutomezzo): float {
        $totaleBilancio = CostoMaterialeSanitario::getTotale($idAssociazione, $idAnno);

        $automezzi = Automezzo::getByAssociazione($idAssociazione, $idAnno);
        $dati = RipartizioneMaterialeSanitario::getRipartizione($idAssociazione, $idAnno);

        $totaleInclusi = $dati['totale_inclusi'] ?? 0;

        $serviziAutomezzo = 0;
        foreach ($dati['righe'] as $riga) {
            if (isset($riga['idAutomezzo']) && $riga['idAutomezzo'] == $idAutomezzo) {
                if ($riga['incluso_riparto']) {
                    $serviziAutomezzo = $riga['totale'] ?? 0;
                }
                break;
            }
        }

        if ($totaleInclusi <= 0 || $serviziAutomezzo <= 0) {
            return 0;
        }

        return round(($serviziAutomezzo / $totaleInclusi) * $totaleBilancio, 2);
    }

    public static function calcolaRipartizione(int $idAssociazione, int $anno, float $totaleBilancio, ?int $idAutomezzo = null): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->when($idAutomezzo, fn($q) => $q->where('idAutomezzo', $idAutomezzo))
            ->get();

        $risultato = [];

        foreach ($automezzi as $mezzo) {
            $kmConvenzioni = DB::table('automezzi_km')
                ->where('idAutomezzo', $mezzo->idAutomezzo)
                ->get();

            $kmTotali = $kmConvenzioni->sum('KMPercorsi');

            foreach ($kmConvenzioni as $riga) {
                $percentuale = $kmTotali > 0 ? round(($riga->KMPercorsi / $kmTotali) * 100, 2) : 0;
                $importo = $kmTotali > 0 ? round(($riga->KMPercorsi / $kmTotali) * $totaleBilancio, 2) : 0;

                $risultato[] = [
                    'idAutomezzo' => $riga->idAutomezzo,
                    'idConvenzione' => $riga->idConvenzione,
                    'km' => $riga->KMPercorsi,
                    'km_totali' => $kmTotali,
                    'percentuale' => $percentuale,
                    'importo' => $importo,
                ];
            }
        }

        return $risultato;
    }

    public static function calcoloRipartizioneOssigeno(int $idAssociazione, int $anno, float $totaleBilancio, ?int $idAutomezzo = null): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->when($idAutomezzo, fn($q) => $q->where('idAutomezzo', $idAutomezzo))
            ->get();

        $risultato = [];
        foreach ($automezzi as $mezzo) {
            $serviziConvenzioni = DB::table('automezzi_servizi')
                ->where('idAutomezzo', $mezzo->idAutomezzo)
                ->get();

            if ($serviziConvenzioni->isEmpty()) {
                continue;
            }
            $serviziTotali = $serviziConvenzioni->sum('NumeroServizi');

            foreach ($serviziConvenzioni as $riga) {
                $percentuale = $serviziTotali > 0 ? round(($riga->NumeroServizi / $serviziTotali) * 100, 2) : 0;
                $importo = $serviziTotali > 0 ? round(($riga->NumeroServizi / $serviziTotali) * $totaleBilancio, 2) : 0;

                $risultato[] = [
                    'idAutomezzo' => $riga->idAutomezzo,
                    'idConvenzione' => $riga->idConvenzione,
                    'NumeroServizi' => $riga->NumeroServizi,
                    'ServiziTotali' => $serviziTotali,
                    'percentuale' => $percentuale,
                    'importo' => $importo,
                ];
            }
        }

        return $risultato;
    }

    public static function calcoloRipartizioneCostiRadio(int $idAssociazione, int $anno, ?int $idAutomezzo = null): array {
        $voci = [
            'ManutenzioneApparatiRadio',
            'MontaggioSmontaggioRadio118',
            'LocazionePonteRadio',
            'AmmortamentoImpiantiRadio'
        ];

        $costiRadio = DB::table('costi_radio')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        if (!$costiRadio) return [];

        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->when($idAutomezzo, fn($q) => $q->where('idAutomezzo', $idAutomezzo))
            ->get();

        $risultato = [];

        foreach ($automezzi as $mezzo) {
            $servizi = DB::table('automezzi_servizi')
                ->where('idAutomezzo', $mezzo->idAutomezzo)
                ->get();

            $totaleServizi = $servizi->sum('NumeroServizi');

            foreach ($servizi as $riga) {
                $percentuale = $totaleServizi > 0 ? round(($riga->NumeroServizi / $totaleServizi) * 100, 2) : 0;

                $ripartizione = [];
                foreach ($voci as $voce) {
                    $importoTotale = $costiRadio->$voce ?? 0;
                    $importo = $totaleServizi > 0 ? round(($riga->NumeroServizi / $totaleServizi) * $importoTotale, 2) : 0;
                    $ripartizione[$voce] = $importo;
                }

                $risultato[] = array_merge([
                    'idAutomezzo' => $mezzo->idAutomezzo,
                    'idConvenzione' => $riga->idConvenzione,
                    'NumeroServizi' => $riga->NumeroServizi,
                    'TotaleServizi' => $totaleServizi,
                    'percentuale' => $percentuale,
                ], $ripartizione);
            }
        }

        return $risultato;
    }

    public static function calcolaRipartizioneTabellaFinale(int $idAssociazione, int $anno, int $idAutomezzo): array {
        $voci = [
            'LEASING/NOLEGGIO A LUNGO TERMINE'      => 'LeasingNoleggio',
            'ASSICURAZIONI'                         => 'Assicurazione',
            'MANUTENZIONE ORDINARIA'                => 'ManutenzioneOrdinaria',
            'MANUTENZIONE STRAORDINARIA AL NETTO RIMBORSI ASSICURATIVI'  => 'ManutenzioneStraordinaria',
            'RIMBORSI ASSICURAZIONE'                => 'RimborsiAssicurazione',
            'PULIZIA E DISINFEZIONE'                => 'PuliziaDisinfezione',
            'CARBURANTI AL NETTO RIMBORSI UTIF'     => 'Carburanti',
            'ADDITIVI'                              => 'Additivi',
            'INTERESSI PASS. F.TO, LEASING, NOL.'   => 'InteressiPassivi',
            'MANUTENZIONE ATTREZZATURA SANITARIA'   => 'ManutenzioneSanitaria',
            'LEASING ATTREZZATURA SANITARIA'        => 'LeasingSanitaria',
            'AMMORTAMENTO AUTOMEZZI'                => 'AmmortamentoMezzi',
            'AMMORTAMENTO ATTREZZATURA SANITARIA'   => 'AmmortamentoSanitaria',
            'ALTRI COSTI MEZZI'                     =>  'AltriCostiMezzi',
        ];

        // Convenzioni attive (per intestare le colonne)
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        $tabella = [];

        // ───── VOCI BASE DAL MODELLO costi_automezzi
        foreach ($voci as $voceLabel => $colDB) {
            $valore = DB::table('costi_automezzi')
                ->where('idAutomezzo', $idAutomezzo)
                ->where('idAnno', $anno)
                ->value($colDB) ?? 0;

            $ripartizione = DB::table('automezzi_km')
                ->where('idAutomezzo', $idAutomezzo)
                ->pluck('KMPercorsi', 'idConvenzione')
                ->toArray();

            $totaleKM = array_sum($ripartizione);

            $riga = ['voce' => $voceLabel, 'totale' => $valore];

            foreach ($convenzioni as $idConv => $nomeConv) {
                $km = $ripartizione[$idConv] ?? 0;
                $importo = ($totaleKM > 0) ? round(($km / $totaleKM) * $valore, 2) : 0;
                $riga[$nomeConv] = $importo;
            }

            $tabella[] = $riga;
        }

        // ───── COSTI RADIO (divisi per servizio)
        $costiRadio = DB::table('costi_radio')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->first();

        $vociRadio = [
            'MANUTENZIONE APPARATI RADIO'     => 'ManutenzioneApparatiRadio',
            'MONTAGGIO/SMONTAGGIO RADIO 118'  => 'MontaggioSmontaggioRadio118',
            'LOCAZIONE PONTE RADIO'           => 'LocazionePonteRadio',
            'AMMORTAMENTO IMPIANTI RADIO'     => 'AmmortamentoImpiantiRadio',
        ];

        $servizi = DB::table('automezzi_servizi')
            ->where('idAutomezzo', $idAutomezzo)
            ->pluck('NumeroServizi', 'idConvenzione')
            ->toArray();

        $totaleServizi = array_sum($servizi);

        foreach ($vociRadio as $voceLabel => $campoDB) {
            $valore = $costiRadio->$campoDB ?? 0;
            $riga = ['voce' => $voceLabel, 'totale' => $valore];

            foreach ($convenzioni as $idConv => $nomeConv) {
                $serv = $servizi[$idConv] ?? 0;
                $importo = ($totaleServizi > 0) ? round(($serv / $totaleServizi) * $valore, 2) : 0;
                $riga[$nomeConv] = $importo;
            }

            $tabella[] = $riga;
        }

        return $tabella;
    }

    public static function calcolaTabellaTotale(int $idAssociazione, int $anno): array {
        $automezzi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->pluck('idAutomezzo');

        // Ottieni le convenzioni una sola volta
        $convenzioni = DB::table('convenzioni')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('Convenzione', 'idConvenzione')
            ->toArray();

        $tabellaTotale = [];

        foreach ($automezzi as $idAutomezzo) {
            $tabella = self::calcolaRipartizioneTabellaFinale($idAssociazione, $anno, $idAutomezzo);

            foreach ($tabella as $riga) {
                $voce = $riga['voce'];

                if (!isset($tabellaTotale[$voce])) {
                    // inizializza tutte le colonne (incluso 'totale' e convenzioni) a 0
                    $tabellaTotale[$voce] = ['voce' => $voce, 'totale' => 0];
                    foreach ($convenzioni as $conv) {
                        $tabellaTotale[$voce][$conv] = 0;
                    }
                }

                // somma i valori
                $tabellaTotale[$voce]['totale'] += floatval($riga['totale'] ?? 0);
                foreach ($convenzioni as $conv) {
                    $tabellaTotale[$voce][$conv] += floatval($riga[$conv] ?? 0);
                }
            }
        }

        return array_values($tabellaTotale);
    }
    public static function getCostiDiretti(int $idAssociazione, int $anno): array {
        return DB::table('costi_diretti')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->pluck('costo', 'idConvenzione')
            ->toArray();
    }

    public static function estraiVociDaRiepilogoDati(int $idAssociazione, int $anno, array $tipologie): array {
        // Recupera l'idRiepilogo per l'associazione e anno
        $idRiepilogo = DB::table('riepiloghi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->value('idRiepilogo');

        if (!$idRiepilogo) {
            return [];
        }

        // Estrai le voci (descrizione) per le tipologie indicate
        $voci = DB::table('riepilogo_dati')
            ->where('idRiepilogo', $idRiepilogo)
            ->whereIn('idTipologiaRiepilogo', $tipologie)
            ->select('idTipologiaRiepilogo', 'descrizione')
            ->distinct()
            ->orderBy('idTipologiaRiepilogo')
            ->orderBy('descrizione')
            ->get();

        // Ritorna un array strutturato per tipologia
        $vociPerTipologia = [];

        foreach ($voci as $voce) {
            $idTipologia = (int) $voce->idTipologiaRiepilogo;
            $descrizione = trim(strtoupper($voce->descrizione));
            $vociPerTipologia[$idTipologia][] = $descrizione;
        }

        return $vociPerTipologia;
    }
}

<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;
use Illuminate\Support\Collection;

class RipartizioneMaterialeSanitario {
    private const RIPARTO_SI = 'SI';
    private const RIPARTO_NO = 'NO';
    private const TABLE_AUTOMEZZI = 'automezzi';

    /**
     * Restituisce la ripartizione con i conteggi per convenzione/automezzo.
     */
    public static function getRipartizione(?int $idAssociazione, int $anno): array {
        // 1. Recupero automezzi filtrati per anno (e associazione se definita)
        $automezziQuery = DB::table(self::TABLE_AUTOMEZZI)
            ->where('idAnno', $anno);

        if (!is_null($idAssociazione)) {
            $automezziQuery->where('idAssociazione', $idAssociazione);
        }

        $automezzi = $automezziQuery
            ->select('idAutomezzo', 'Automezzo', 'Targa', 'CodiceIdentificativo', 'incluso_riparto')
            ->get()
            ->keyBy('idAutomezzo');

        // 2. Convenzioni disponibili
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);

        // 3. Servizi per (automezzo, convenzione)
        $servizi = DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $automezzi->keys())
            ->whereIn('idConvenzione', $convenzioni->pluck('idConvenzione'))
            ->select('idAutomezzo', 'idConvenzione', 'NumeroServizi')
            ->get();

        $serviziIndicizzati = $servizi->keyBy(fn($s) => $s->idAutomezzo . '-' . $s->idConvenzione);

        // 4. Calcolo righe per DataTable
        $righe = [];
        $totaleInclusi = 0;
        $totaliPerConvenzione = [];

        foreach ($automezzi as $id => $auto) {
            $incluso = filter_var($auto->incluso_riparto, FILTER_VALIDATE_BOOLEAN);

            $riga = [
                'idAutomezzo' => $id,
                'Automezzo' => $auto->Automezzo,
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

                // Totali per la riga finale
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

        // 5. Riga finale di TOTALE
        $rigaTotale = [
            'idAutomezzo' => null,
            'Automezzo' => 'TOTALE',
            'Targa' => '',
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


    /**
     * Restituisce tutti gli automezzi per la vista di modifica dei flag.
     */
    public static function getAutomezziPerEdit(?int $idAssociazione, int $anno) {
        $query = DB::table(self::TABLE_AUTOMEZZI)
            ->where('idAnno', $anno);

        if (!is_null($idAssociazione)) {
            $query->where('idAssociazione', $idAssociazione);
        }

        return $query
            ->select('idAutomezzo', 'Automezzo', 'Targa', 'CodiceIdentificativo', 'incluso_riparto')
            ->orderBy('Automezzo')
            ->get();
    }

    /**
     * Aggiorna il flag di un singolo automezzo.
     */
    public static function aggiornaInclusione(int $idAutomezzo, bool $incluso): void {
        DB::table(self::TABLE_AUTOMEZZI)
            ->where('idAutomezzo', $idAutomezzo)
            ->update([
                'incluso_riparto' => $incluso ? 1 : 0,
                'updated_at'      => now(),
            ]);
    }

    /**
     * Aggiorna i flag di inclusione per più automezzi.
     * Tutti quelli **non presenti** nell’array saranno marcati come NO.
     */
    public static function aggiornaInclusioni(array $idInclusi, ?int $idAssociazione = null, ?int $anno = null): void {
        $query = DB::table(self::TABLE_AUTOMEZZI);
        // … filtri …
        // 1) Tutti a 0
        $query->update([
            'incluso_riparto' => 0,
            'updated_at'      => now(),
        ]);

        // 2) Solo quelli selezionati a 1
        if (!empty($idInclusi)) {
            DB::table(self::TABLE_AUTOMEZZI)
                ->whereIn('idAutomezzo', $idInclusi)
                ->update([
                    'incluso_riparto' => 1,
                    'updated_at'      => now(),
                ]);
        }
    }

    public static function getTotaleServizi(Collection $automezzi, int $anno): int {
        if ($automezzi->isEmpty()) {
            return 0;
        }

        $idAutomezziInclusi = $automezzi
            ->filter(fn($a) => filter_var($a->incluso_riparto, FILTER_VALIDATE_BOOLEAN))
            ->pluck('idAutomezzo');

        if ($idAutomezziInclusi->isEmpty()) {
            return 0;
        }

        // Prendo una delle associazioni degli automezzi inclusi (assumo siano tutti della stessa)
        $idAssociazione = $automezzi->first()->idAssociazione ?? null;

        // Convenzioni da considerare
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);
        $idConvenzioni = $convenzioni->pluck('idConvenzione');

        if ($idConvenzioni->isEmpty()) {
            return 0;
        }

        // Somma tutti i servizi svolti da automezzi inclusi e per convenzioni valide
        return DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $idAutomezziInclusi)
            ->whereIn('idConvenzione', $idConvenzioni)
            ->sum('NumeroServizi');
    }
}

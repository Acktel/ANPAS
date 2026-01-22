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
        // 1) Automezzi inclusi nel riparto
        $automezziQuery = DB::table(self::TABLE_AUTOMEZZI)
            ->where('idAnno', $anno);

        if (!is_null($idAssociazione)) {
            $automezziQuery->where('idAssociazione', $idAssociazione);
        }

        $automezzi = $automezziQuery
            ->select('idAutomezzo', 'Targa', 'CodiceIdentificativo', 'incluso_riparto')
            ->where('incluso_riparto', 1)
            ->get()
            ->keyBy('idAutomezzo');

        // niente mezzi? ritorna coerente
        if ($automezzi->isEmpty()) {
            return [
                'convenzioni'    => collect(),
                'righe'          => [],
                'totale_inclusi' => 0,
            ];
        }

        // 2) Convenzioni + flag materiale_fornito_asl
        // Se il tuo Convenzione::getByAssociazioneAnno non include il campo, qui lo reintegro.
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);

        if ($convenzioni->isEmpty()) {
            return [
                'convenzioni'    => $convenzioni,
                'righe'          => [],
                'totale_inclusi' => 0,
            ];
        }

        $convIds = $convenzioni->pluck('idConvenzione')->map(fn($v) => (int)$v)->all();

        // Mappa convId => bool materiale_fornito_asl
        // (usa DB diretto per essere sicuro di avere il campo)
        $convAslMap = DB::table('convenzioni')
            ->where('idAnno', $anno)
            ->when(!is_null($idAssociazione), function ($q) use ($idAssociazione) {
                $q->where('idAssociazione', $idAssociazione);
            })
            ->whereIn('idConvenzione', $convIds)
            ->pluck('materiale_fornito_asl', 'idConvenzione')
            ->map(fn($v) => ((int)$v === 1))
            ->toArray();

        // 3) Servizi (automezzo, convenzione)
        $servizi = DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $automezzi->keys())
            ->whereIn('idConvenzione', $convIds)
            ->select('idAutomezzo', 'idConvenzione', 'NumeroServizi')
            ->get();

        $serviziIndicizzati = $servizi->keyBy(fn($s) => $s->idAutomezzo . '-' . $s->idConvenzione);

        // 4) Calcolo righe
        $righe               = [];
        $totaleInclusi       = 0;
        $totaliPerConvenzione = [];

        foreach ($automezzi as $id => $auto) {
            $incluso = filter_var($auto->incluso_riparto, FILTER_VALIDATE_BOOLEAN);

            $riga = [
                'idAutomezzo'        => (int)$id,
                'Targa'              => $auto->Targa,
                'CodiceIdentificativo' => $auto->CodiceIdentificativo,
                'incluso_riparto'    => $incluso,
                'valori'             => [],
                'totale'             => 0,
            ];

            foreach ($convenzioni as $conv) {
                $cid = (int)$conv->idConvenzione;

                // SE materiale fornito ASL => non conteggiare servizi
                if (!empty($convAslMap[$cid])) {
                    $num = 0;
                } else {
                    $key = $id . '-' . $cid;
                    $num = isset($serviziIndicizzati[$key]) ? (int)$serviziIndicizzati[$key]->NumeroServizi : 0;
                }

                $riga['valori'][$cid] = $num;
                $riga['totale']      += $num;

                if (!isset($totaliPerConvenzione[$cid])) {
                    $totaliPerConvenzione[$cid] = 0;
                }
                $totaliPerConvenzione[$cid] += $incluso ? $num : 0;
            }

            if ($incluso) {
                $totaleInclusi += $riga['totale'];
            }

            $righe[(int)$id] = $riga;
        }

        // 5) Riga finale TOTALE
        $rigaTotale = [
            'idAutomezzo'     => null,
            'Targa'           => 'TOTALE',
            'CodiceIdentificativo' => '',
            'incluso_riparto' => true,
            'valori'          => [],
            'totale'          => $totaleInclusi,
            'is_totale'       => true,
        ];

        foreach ($convenzioni as $conv) {
            $cid = (int)$conv->idConvenzione;
            $rigaTotale['valori'][$cid] = $totaliPerConvenzione[$cid] ?? 0;
        }

        $righe['totale'] = $rigaTotale;

        return [
            'convenzioni'    => $convenzioni,
            'righe'          => $righe,
            'totale_inclusi' => $totaleInclusi,
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
            ->select('idAutomezzo', 'Targa', 'CodiceIdentificativo', 'incluso_riparto')
            ->orderBy('idAutomezzo')
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

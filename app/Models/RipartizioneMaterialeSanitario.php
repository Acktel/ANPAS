<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;

class RipartizioneMaterialeSanitario {
    private const RIPARTO_SI = 'SI';
    private const RIPARTO_NO = 'NO';
    private const TABLE_AUTOMEZZI = 'automezzi';

    /**
     * Restituisce la ripartizione con i conteggi per convenzione/automezzo.
     */
    public static function getRipartizione(?int $idAssociazione, int $anno): array {
        // 1. Recupero automezzi
        $automezziQuery = DB::table(self::TABLE_AUTOMEZZI)
            ->where('idAnno', $anno);

        if (!is_null($idAssociazione)) {
            $automezziQuery->where('idAssociazione', $idAssociazione);
        }

        $automezzi = $automezziQuery
            ->select('idAutomezzo', 'Automezzo', 'Targa', 'CodiceIdentificativo', 'incluso_riparto')
            ->get()
            ->keyBy('idAutomezzo');

        // 2. Convenzioni da model centralizzato (gestisce internamente null)
        $convenzioni = Convenzione::getByAssociazioneAnno($idAssociazione, $anno);

        // 3. Servizi svolti per (automezzo, convenzione)
        $servizi = DB::table('automezzi_servizi')
            ->whereIn('idAutomezzo', $automezzi->keys())
            ->whereIn('idConvenzione', $convenzioni->pluck('idConvenzione'))
            ->select('idAutomezzo', 'idConvenzione', 'NumeroServizi')
            ->get();

        // 4. Mappa indicizzata "idAutomezzo-idConvenzione" → NumeroServizi
        $serviziIndicizzati = $servizi->keyBy(fn($s) => $s->idAutomezzo . '-' . $s->idConvenzione);

        // 5. Composizione righe
        $righe = [];
        $totaleInclusi = 0;

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
                $num = isset($serviziIndicizzati[$key]) ? (int)$serviziIndicizzati[$key]->NumeroServizi : 0;
                $riga['valori'][$conv->idConvenzione] = $num;
                $riga['totale'] += $num;
            }

            if ($incluso) {
                $totaleInclusi += $riga['totale'];
            }

            $righe[$id] = $riga;
        }

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

}

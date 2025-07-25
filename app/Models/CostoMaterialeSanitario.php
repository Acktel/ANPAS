<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class CostoMaterialeSanitario extends Model {
    protected $table = 'costi_materiale_sanitario';

    protected $fillable = [
        'idAssociazione',
        'idAnno',
        'TotaleBilancio',
    ];

    public $timestamps = true;

    /**
     * Restituisce il totale a bilancio per l'associazione e l'anno specificati.
     */
    public static function getTotale(int $idAssociazione, int $anno): float {
        // Verifica se ci sono automezzi inclusi nel riparto
        $haInclusi = DB::table('automezzi')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->where('incluso_riparto', 1)
            ->exists();

        if (! $haInclusi) {
            return 0;
        }

        return self::where('idAssociazione', $idAssociazione)
            ->where('idAnno', $anno)
            ->value('TotaleBilancio') ?? 0;
    }


    /**
     * Inserisce o aggiorna il totale a bilancio per l'associazione e l'anno specificati.
     */
    public static function upsertTotale(int $idAssociazione, int $idAnno, float $valore): void {
        self::updateOrCreate(
            [
                'idAssociazione' => $idAssociazione,
                'idAnno' => $idAnno,
            ],
            [
                'TotaleBilancio' => $valore,
            ]
        );
    }
}

<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use App\Services\RipartizioneCostiService;

class RotazioneMezzi {
    public static function getConvRotazione(int $idAssociazione, int $idAnno)
    {
        $rows = \DB::table('convenzioni')
            ->select('idConvenzione','Convenzione','ordinamento')
            ->where('idAssociazione', $idAssociazione)
            ->where('idAnno', $idAnno)
            ->where('abilita_rot_sost', 1)
            ->orderBy('ordinamento')->orderBy('idConvenzione')
            ->get();

        return $rows->filter(fn($c) => RipartizioneCostiService::isRegimeRotazione((int)$c->idConvenzione))
                    ->values();
    }   
}
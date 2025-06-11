<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RegistroExport implements FromQuery, WithHeadings, ShouldAutoSize
{
    protected int $asso;
    protected int $anno;

    public function __construct(int $idAssociazione, int $idAnno)
    {
        $this->asso = $idAssociazione;
        $this->anno = $idAnno;
    }

    /**
     * Restituisce la query che popola le righe.
     */
    public function query()
    {
        return DB::table('riepiloghi as r')
            ->join('riepilogo_dati as d', 'r.idRiepilogo', '=', 'd.idRiepilogo')
            ->where('r.idAssociazione', $this->asso)
            ->where('r.idAnno', $this->anno)
            ->select([
                'd.descrizione',
                'd.preventivo',
                'd.consuntivo',
            ])->orderBy('d.id') ;
    }

    /**
     * Intestazioni di colonna.
     */
    public function headings(): array
    {
        return [
            'Descrizione',
            'Preventivo',
            'Consuntivo',
        ];
    }
}

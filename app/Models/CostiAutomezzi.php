<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class CostiAutomezzi {
    protected const TABLE = 'costi_automezzi';

    public static function getByAutomezzo(int $idAutomezzo, int $anno): ?object {
        return DB::table(self::TABLE)
            ->where('idAutomezzo', $idAutomezzo)
            ->where('idAnno', $anno)
            ->first();
    }

    public static function getAllByAnno(int $anno): \Illuminate\Support\Collection {
        return DB::table(self::TABLE)
            ->where('idAnno', $anno)
            ->get();
    }

    public static function updateOrInsert(array $data): void {
        DB::table(self::TABLE)->updateOrInsert(
            ['idAutomezzo' => $data['idAutomezzo'], 'idAnno' => $data['idAnno']],
            collect($data)->except(['idAutomezzo', 'idAnno'])->toArray()
        );
    }

    public static function deleteByAutomezzo(int $idAutomezzo, int $anno): void {
        DB::table(self::TABLE)
            ->where('idAutomezzo', $idAutomezzo)
            ->where('idAnno', $anno)
            ->delete();
    }

    public static function getOrEmpty(int $idAutomezzo, int $anno): object {
        return self::getByAutomezzo($idAutomezzo, $anno) ?? (object)[
            'idAutomezzo' => $idAutomezzo,
            'LeasingNoleggio' => 0,
            'Assicurazione' => 0,
            'ManutenzioneOrdinaria' => 0,
            'ManutenzioneStraordinaria' => 0,
            'RimborsiAssicurazione' => 0,
            'PuliziaDisinfezione' => 0,
            'Carburanti' => 0,
            'Additivi' => 0,
            'RimborsiUTF' => 0,
            'InteressiPassivi' => 0,
            'AltriCostiMezzi' => 0,
            'ManutenzioneSanitaria' => 0,
            'LeasingSanitaria' => 0,
            'AmmortamentoMezzi' => 0,
            'AmmortamentoSanitaria' => 0,
        ];
    }
}

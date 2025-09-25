<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class CostiPersonale {
    protected const TABLE = 'costi_personale';

    public static function getByDipendente(int $idDipendente, int $anno): ?object {
        return DB::table(self::TABLE)
            ->where('idDipendente', $idDipendente)
            ->where('idAnno', $anno)
            ->first();
    }

    public static function createEmptyRecord(int $idDipendente, int $anno): object {
        $dip = Dipendente::getCognomeNome($idDipendente);

        return (object) [
            'id' => null,
            'idDipendente' => $idDipendente,
            'Retribuzioni' => 0.0,
            'OneriSocialiInps' => 0.0,
            'OneriSocialiInail' => 0.0,
            'TFR' => 0.0,
            'Consulenze' => 0.0,
            'Totale' => 0.0,
            'idAnno' => $anno,
            'DipendenteNome' => $dip->DipendenteNome ?? '',
            'DipendenteCognome' => $dip->DipendenteCognome ?? '',
        ];
    }

    public static function updateOrInsert(array $data): void {
        DB::table(self::TABLE)->updateOrInsert(
            ['idDipendente' => $data['idDipendente'], 'idAnno' => $data['idAnno']],
            [
                'Retribuzioni' => $data['Retribuzioni'],
                'OneriSocialiInps' => $data['OneriSocialiInps'],
                'OneriSocialiInail' => $data['OneriSocialiInail'],
                'TFR' => $data['TFR'],
                'Consulenze' => $data['Consulenze'],
                'Totale' => $data['Totale'],
            ]
        );
    }

    public static function deleteByDipendente(int $idDipendente, int $anno): void {
        DB::table(self::TABLE)
            ->where('idDipendente', $idDipendente)
            ->where('idAnno', $anno)
            ->delete();
    }

    public static function getAllByAnno(int $anno): \Illuminate\Support\Collection {
        return DB::table(self::TABLE)->where('idAnno', $anno)->get();
    }

    public static function getWithDipendente(int $idDipendente, int $anno): object {
        $record = self::getByDipendente($idDipendente, $anno);

        if ($record) {
            $dip = Dipendente::getCognomeNome($idDipendente);

            return (object) array_merge((array) $record, [
                'DipendenteNome' => $dip->DipendenteNome ?? '',
                'DipendenteCognome' => $dip->DipendenteCognome ?? '',
            ]);
        }

        return self::createEmptyRecord($idDipendente, $anno);
    }

    
}

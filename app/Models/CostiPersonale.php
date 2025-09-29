<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class CostiPersonale
{
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

        // base
        'Retribuzioni'      => 0.0,
        'OneriSocialiInps'  => 0.0,
        'OneriSocialiInail' => 0.0,
        'TFR'               => 0.0,
        'Consulenze'        => 0.0,

        // diretti
        'costo_diretto_Retribuzioni'      => 0.0,
        'costo_diretto_OneriSocialiInps'  => 0.0,
        'costo_diretto_OneriSocialiInail' => 0.0,
        'costo_diretto_TFR'               => 0.0,
        'costo_diretto_Consulenze'        => 0.0,

        'Totale' => 0.0,
        'idAnno' => $anno,

        'DipendenteNome'    => $dip->DipendenteNome ?? '',
        'DipendenteCognome' => $dip->DipendenteCognome ?? '',
      ];
    }

    public static function updateOrInsert(array $data): void {
      DB::table(self::TABLE)->updateOrInsert(
        ['idDipendente' => $data['idDipendente'], 'idAnno' => $data['idAnno']],
        [
          // base (solo schema nuovo)
          'Retribuzioni'      => (float)$data['Retribuzioni'],
          'OneriSocialiInps'  => (float)($data['OneriSocialiInps']  ?? 0),
          'OneriSocialiInail' => (float)($data['OneriSocialiInail'] ?? 0),
          'TFR'               => (float)$data['TFR'],
          'Consulenze'        => (float)$data['Consulenze'],

          // diretti
          'costo_diretto_Retribuzioni'      => (float)($data['costo_diretto_Retribuzioni']      ?? 0),
          'costo_diretto_OneriSocialiInps'  => (float)($data['costo_diretto_OneriSocialiInps']  ?? 0),
          'costo_diretto_OneriSocialiInail' => (float)($data['costo_diretto_OneriSocialiInail'] ?? 0),
          'costo_diretto_TFR'               => (float)($data['costo_diretto_TFR']               ?? 0),
          'costo_diretto_Consulenze'        => (float)($data['costo_diretto_Consulenze']        ?? 0),

          'Totale' => (float)$data['Totale'],
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
      return DB::table(self::TABLE)
        ->selectRaw('
          id,
          idDipendente,
          idAnno,

          (COALESCE(Retribuzioni,0) + COALESCE(costo_diretto_Retribuzioni,0))                     AS Retribuzioni,
          (COALESCE(OneriSocialiInps,0) + COALESCE(costo_diretto_OneriSocialiInps,0))             AS OneriSocialiInps,
          (COALESCE(OneriSocialiInail,0) + COALESCE(costo_diretto_OneriSocialiInail,0))           AS OneriSocialiInail,
          (COALESCE(TFR,0) + COALESCE(costo_diretto_TFR,0))                                       AS TFR,
          (COALESCE(Consulenze,0) + COALESCE(costo_diretto_Consulenze,0))                         AS Consulenze,

          (
            (COALESCE(Retribuzioni,0) + COALESCE(costo_diretto_Retribuzioni,0))
          + (COALESCE(OneriSocialiInps,0)  + COALESCE(costo_diretto_OneriSocialiInps,0))
          + (COALESCE(OneriSocialiInail,0) + COALESCE(costo_diretto_OneriSocialiInail,0))
          + (COALESCE(TFR,0)               + COALESCE(costo_diretto_TFR,0))
          + (COALESCE(Consulenze,0)        + COALESCE(costo_diretto_Consulenze,0))
          ) AS Totale,

          created_at,
          updated_at
        ')
        ->where('idAnno', $anno)
        ->get();
    }

    public static function getWithDipendente(int $idDipendente, int $anno): object {
      $record = self::getByDipendente($idDipendente, $anno);

      if ($record) {
        $dip = Dipendente::getCognomeNome($idDipendente);

        foreach ([
          'costo_diretto_Retribuzioni',
          'costo_diretto_OneriSocialiInps',
          'costo_diretto_OneriSocialiInail',
          'costo_diretto_TFR',
          'costo_diretto_Consulenze',
        ] as $k) {
          if (!property_exists($record, $k)) {
            $record->{$k} = 0.0;
          }
        }

        return (object) array_merge((array)$record, [
          'DipendenteNome'    => $dip->DipendenteNome ?? '',
          'DipendenteCognome' => $dip->DipendenteCognome ?? '',
        ]);
      }

      return self::createEmptyRecord($idDipendente, $anno);
    }
}

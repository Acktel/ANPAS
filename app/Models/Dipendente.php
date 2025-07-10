<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class Dipendente {
    protected const TABLE = 'dipendenti';

    public static function getAll(int $anno): Collection {
        return DB::table(self::TABLE . ' as d')
            ->join('associazioni as a', 'd.idAssociazione', '=', 'a.idAssociazione')
            ->where('d.idAnno', $anno)
            ->select([
                'd.*',
                'a.Associazione',
            ])->get();
    }

    public static function getByAssociazione(?int $idAssociazione, int $anno): Collection {
        return DB::table(self::TABLE . ' as d')
            ->join('associazioni as a', 'd.idAssociazione', '=', 'a.idAssociazione')
            ->when($idAssociazione, fn($q) => $q->where('d.idAssociazione', $idAssociazione))
            ->where('d.idAnno', $anno)
            ->select(['d.*', 'a.Associazione'])
            ->get();
    }

    public static function getAutisti(int $anno): Collection {
        $dipendenti = self::getAll($anno);

        $map = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->pluck('qualifiche.nome', 'dipendenti_qualifiche.idDipendente')
            ->groupBy('idDipendente')
            ->map(fn($q) => $q->toArray());

        return $dipendenti->filter(
            fn($d) =>
            isset($map[$d->idDipendente]) &&
                collect($map[$d->idDipendente])->contains(fn($q) => str_contains($q, 'AUTISTA'))
        );
    }

    public static function getAltri(int $anno): Collection {
        $dipendenti = self::getAll($anno);

        $map = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->pluck('qualifiche.nome', 'dipendenti_qualifiche.idDipendente')
            ->groupBy('idDipendente')
            ->map(fn($q) => $q->toArray());

        return $dipendenti->reject(
            fn($d) =>
            isset($map[$d->idDipendente]) &&
                collect($map[$d->idDipendente])->contains(fn($q) => str_contains($q, 'AUTISTA'))
        );
    }

    public static function getOne(int $idDipendente): ?object {
        return DB::table(self::TABLE)->where('idDipendente', $idDipendente)->first();
    }

    public static function storeDipendente(array $data) {
        $qualifiche = $data['Qualifica'] ?? [];
        unset($data['Qualifica']);

        $data['created_at'] = $data['updated_at'] = now();

        $id = DB::table(self::TABLE)->insertGetId($data);

        foreach ($qualifiche as $idQualifica) {
            DB::table('dipendenti_qualifiche')->insert([
                'idDipendente' => $id,
                'idQualifica' => $idQualifica,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('dipendenti.index')->with('success', 'Dipendente creato correttamente.');
    }


    public static function updateDipendente(int $id, array $data) {
        $qualifiche = $data['Qualifica'] ?? [];
        unset($data['Qualifica']);

        $data['updated_at'] = now();

        DB::table(self::TABLE)->where('idDipendente', $id)->update($data);

        // Cancella le qualifiche precedenti e inserisce le nuove
        DB::table('dipendenti_qualifiche')->where('idDipendente', $id)->delete();

        // Recupera mappa nome → id per tutte le qualifiche esistenti
        $mapQualifiche = DB::table('qualifiche')->pluck('id', 'nome');

        foreach ($qualifiche as $nomeQualifica) {
            if (isset($mapQualifiche[$nomeQualifica])) {
                DB::table('dipendenti_qualifiche')->insert([
                    'idDipendente' => $id,
                    'idQualifica' => $mapQualifiche[$nomeQualifica],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->route('dipendenti.index')->with('success', 'Dipendente aggiornato correttamente.');
    }


    public static function getQualifiche(): Collection {
        return DB::table('qualifiche')
            ->select('id', 'nome')
            ->groupBy('id', 'nome')
            ->orderBy('nome')
            ->get();
    }


    public static function getAnni(): Collection {
        return DB::table('anni')->select('idAnno', 'anno')->orderByDesc('anno')->get();
    }

    public static function getContrattiApplicati(): Collection {
        return DB::table('contratti_applicati')->select('nome')->orderBy('nome')->get();
    }

    public static function getLivelliMansione(): array {
        return DB::table(self::TABLE)->distinct()->pluck('LivelloMansione')->filter()->unique()->sort()->values()->toArray();
    }

    public static function getQualificheByDipendente(int $idDipendente): array {
        return DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->where('dipendenti_qualifiche.idDipendente', $idDipendente)
            ->pluck('qualifiche.nome')
            ->toArray();
    }

    public static function getNomiQualifiche(int $idDipendente): string {
        return implode(', ', self::getQualificheByDipendente($idDipendente));
    }

    public static function getAssociazioni($user, bool $isImpersonating): Collection {
        return ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && !$isImpersonating)
            ? DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get()
            : DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->where('idAssociazione', $user->IdAssociazione)
            ->whereNull('deleted_at')
            ->get();
    }

    public static function getAutistiEBarellieri(int $anno, ?int $idAssociazione = null): Collection {
        $query = DB::table('dipendenti as d')
            ->join('dipendenti_qualifiche as dq', 'd.idDipendente', '=', 'dq.idDipendente')
            ->join('qualifiche as q', 'dq.idQualifica', '=', 'q.id')
            ->join('associazioni as a', 'd.idAssociazione', '=', 'a.idAssociazione')
            ->where('d.idAnno', $anno)
            ->where(function ($query) {
                $query->where('q.nome', 'like', '%AUTISTA%')
                    ->orWhere('q.nome', 'like', '%BARELLIERE%');
            });

        if ($idAssociazione) {
            $query->where('d.idAssociazione', $idAssociazione);
        }

        return $query->select([
            'd.*',
            'a.Associazione',
        ])
            ->distinct()
            ->get();
    }


    public static function getAllWithQualifiche(): Collection {
        $anno = session('anno_riferimento', now()->year);

        return DB::table('dipendenti as d')
            ->join('associazioni as a', 'd.idAssociazione', '=', 'a.idAssociazione')
            ->leftJoin('dipendenti_qualifiche as dq', 'd.idDipendente', '=', 'dq.idDipendente')
            ->leftJoin('qualifiche as q', 'dq.idQualifica', '=', 'q.idQualifica')
            ->where('d.idAnno', $anno)
            ->select([
                'd.idDipendente',
                'a.Associazione',
                'd.idAnno',
                'd.DipendenteNome',
                'd.DipendenteCognome',
                'd.LivelloMansione',
                'd.created_at',
                // <<< qui il DISTINCT sul nome:
                DB::raw('GROUP_CONCAT(DISTINCT q.nome ORDER BY q.nome SEPARATOR ", ") as Qualifica'),
            ])
            ->groupBy('d.idDipendente')
            ->get();
    }
}

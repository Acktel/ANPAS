<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Traits\TracksUserActions;

class Dipendente {
    use TracksUserActions;

    protected const TABLE = 'dipendenti';

    private static function baseQueryWithUsers(int $anno) {
        return DB::table(self::TABLE . ' as d')
            ->join('associazioni as a', 'd.idAssociazione', '=', 'a.idAssociazione')
            ->leftJoin('users as uc', 'd.created_by', '=', 'uc.id')
            ->leftJoin('users as uu', 'd.updated_by', '=', 'uu.id')
            ->leftJoin('dipendenti_qualifiche as dq', 'dq.idDipendente', '=', 'd.idDipendente')
            ->leftJoin('qualifiche as q', 'q.id', '=', 'dq.idQualifica')
            ->leftJoin('dipendenti_livelli_mansione as dlm', 'dlm.idDipendente', '=', 'd.idDipendente')
            ->leftJoin('livello_mansione as lm', 'lm.id', '=', 'dlm.idLivelloMansione')
            ->where('d.idAnno', $anno)
            ->groupBy('d.idDipendente')
            ->selectRaw('
                d.idDipendente,
                MIN(d.idAssociazione) as idAssociazione,
                MIN(d.idAnno) as idAnno,
                MIN(d.DipendenteNome) as DipendenteNome,
                MIN(d.DipendenteCognome) as DipendenteCognome,
                MIN(d.ContrattoApplicato) as ContrattoApplicato,
                MIN(a.Associazione) as Associazione,
                MIN(uc.username) as created_by_name,
                MIN(uu.username) as updated_by_name,
                GROUP_CONCAT(DISTINCT q.nome ORDER BY q.nome SEPARATOR ", ") as Qualifica,
                GROUP_CONCAT(DISTINCT lm.nome ORDER BY lm.nome SEPARATOR ", ") as LivelloMansione,
                MIN(d.created_at) as created_at,
                MIN(d.updated_at) as updated_at
            ');
    }

    public static function getAll(int $anno): Collection {
        return self::baseQueryWithUsers($anno)->get();
    }

    public static function getByAssociazione(?int $idAssociazione, int $anno): Collection {
        return self::baseQueryWithUsers($anno)
            ->when($idAssociazione, fn($q) => $q->where('d.idAssociazione', $idAssociazione))
            ->get();
    }

    public static function getOne(int $idDipendente): ?object {
        return DB::table(self::TABLE)->where('idDipendente', $idDipendente)->first();
    }

    public static function getAutisti(int $anno): Collection {
        $dipendenti = self::getAll($anno);
        $map = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->select('dipendenti_qualifiche.idDipendente', 'qualifiche.nome')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($q) => $q->pluck('nome')->toArray());

        return $dipendenti->filter(
            fn($d) =>
            isset($map[$d->idDipendente]) &&
                collect($map[$d->idDipendente])->contains(fn($q) => str_contains($q, 'AUTISTA'))
        );
    }

    public static function getAmministrativi(int $anno): Collection {
        $dipendenti = self::getAll($anno);
        $map = DB::table('dipendenti_qualifiche')
            ->select('idDipendente', 'idQualifica')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($items) => $items->pluck('idQualifica')->toArray());

        return $dipendenti->filter(fn($d) => in_array(3, $map[$d->idDipendente] ?? []));
    }

    public static function getAltri(int $anno): Collection {
        $dipendenti = self::getAll($anno);
        $map = DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->select('dipendenti_qualifiche.idDipendente', 'qualifiche.nome')
            ->get()
            ->groupBy('idDipendente')
            ->map(fn($q) => $q->pluck('nome')->toArray());

        return $dipendenti->reject(
            fn($d) =>
            isset($map[$d->idDipendente]) &&
                collect($map[$d->idDipendente])->contains(fn($q) => str_contains($q, 'AUTISTA'))
        );
    }

    public static function getLivelliMansione(): Collection {
        return DB::table('livello_mansione')->select('id', 'nome')->orderBy('nome')->get();
    }

    public static function getLivelliMansioneByDipendente(int $idDipendente): array {
        return DB::table('dipendenti_livelli_mansione')
            ->where('idDipendente', $idDipendente)
            ->pluck('idLivelloMansione')
            ->toArray();
    }

    public static function storeDipendente(array $data) {
        $qualifiche = $data['Qualifica'] ?? [];
        $livelli = $data['LivelloMansione'] ?? [];

        unset($data['Qualifica'], $data['LivelloMansione']);

        $userId = auth()->id();
        $now = now();
        $data['created_at'] = $data['updated_at'] = $now;
        $data['created_by'] = $data['updated_by'] = $userId;

        $id = DB::table(self::TABLE)->insertGetId($data);

        foreach (array_unique($qualifiche) as $idQualifica) {
            DB::table('dipendenti_qualifiche')->insert([
                'idDipendente'   => $id,
                'idQualifica'    => $idQualifica,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        foreach (array_unique($livelli) as $idLivello) {
            DB::table('dipendenti_livelli_mansione')->insert([
                'idDipendente'        => $id,
                'idLivelloMansione'   => $idLivello,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        return redirect()->route('dipendenti.index')->with('success', 'Dipendente creato correttamente.');
    }

    public static function updateDipendente(int $id, array $data) {
        $qualifiche = $data['Qualifica'] ?? [];
        $livelli = $data['LivelloMansione'] ?? [];

        unset($data['Qualifica'], $data['LivelloMansione']);
        $data['updated_at'] = now();
        $data['updated_by'] = auth()->id();

        DB::table(self::TABLE)->where('idDipendente', $id)->update($data);

        DB::table('dipendenti_qualifiche')->where('idDipendente', $id)->delete();
        foreach ($qualifiche as $idQualifica) {
            DB::table('dipendenti_qualifiche')->insert([
                'idDipendente' => $id,
                'idQualifica' => $idQualifica,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('dipendenti_livelli_mansione')->where('idDipendente', $id)->delete();
        foreach ($livelli as $idLivello) {
            DB::table('dipendenti_livelli_mansione')->insert([
                'idDipendente' => $id,
                'idLivelloMansione' => $idLivello,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('dipendenti.index')->with('success', 'Dipendente aggiornato correttamente.');
    }

    public static function getQualifiche(): Collection {
        return DB::table('qualifiche')->select('id', 'nome')->orderBy('nome')->get();
    }

    public static function getQualificheByDipendente(int $idDipendente): array {
        return DB::table('dipendenti_qualifiche')
            ->where('idDipendente', $idDipendente)
            ->pluck('idQualifica')
            ->toArray();
    }

    public static function getNomiQualifiche(int $idDipendente): string {
        return DB::table('dipendenti_qualifiche')
            ->join('qualifiche', 'dipendenti_qualifiche.idQualifica', '=', 'qualifiche.id')
            ->where('dipendenti_qualifiche.idDipendente', $idDipendente)
            ->pluck('qualifiche.nome')
            ->implode(', ');
    }

    public static function getContrattiApplicati(): Collection {
        return DB::table('contratti_applicati')->select('nome')->orderBy('nome')->get();
    }

    public static function getAnni(): Collection {
        return DB::table('anni')->select('idAnno', 'anno')->orderByDesc('anno')->get();
    }

    public static function getAssociazioni($user, bool $isImpersonating): Collection {
        return ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) && !$isImpersonating)
            ? DB::table('associazioni')->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->whereNot("idAssociazione", 1)
            ->orderBy('Associazione')->get()
            : DB::table('associazioni')->select('idAssociazione', 'Associazione')
            ->where('idAssociazione', $user->IdAssociazione)
            ->whereNull('deleted_at')
            ->whereNot("idAssociazione", 1)->get();
    }

    public static function getAutistiEBarellieri(int $anno, $idAssociazione = null) {
        return DB::table('dipendenti as d')
            ->join('dipendenti_qualifiche as dq', 'd.idDipendente', '=', 'dq.idDipendente')
            ->join('qualifiche as q', 'dq.idQualifica', '=', 'q.id')
            ->join('associazioni as a', 'd.idAssociazione', '=', 'a.idAssociazione')
            ->where('d.idAnno', $anno)
            ->where('dq.idQualifica', 1) // Filtro su idQualifica (pivot)
            ->when($idAssociazione !== null, function ($query) use ($idAssociazione) {
                $query->where('d.idAssociazione', $idAssociazione);
            })
            ->select(
                'd.idDipendente',
                'd.DipendenteNome',
                'd.DipendenteCognome',
                'd.idAssociazione',
                'a.Associazione'
            )
            ->orderBy('d.DipendenteCognome')
            ->get();
    }

    public static function getCognomeNome(int $idDipendente): ?object {
        return DB::table('dipendenti')
            ->where('idDipendente', $idDipendente)
            ->select('DipendenteNome', 'DipendenteCognome')
            ->first();
    }
}

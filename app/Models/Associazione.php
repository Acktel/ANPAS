<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Associazione {
    protected const TABLE = 'associazioni';

    /**
     * Restituisce i dati per DataTables, includendo anche l'ID del Supervisor/Admin/SuperAdmin
     */
    public static function getAll($request) {
        $base = DB::table(self::TABLE . ' as a')
            ->leftJoin('users as u', function ($join) {
                $join->on('a.IdAssociazione', '=', 'u.IdAssociazione');
            })
            ->leftJoin('users as uc', 'a.created_by', '=', 'uc.id')
            ->leftJoin('users as uu', 'a.updated_by', '=', 'uu.id')
            ->select(
                'a.IdAssociazione',
                'a.Associazione',
                'a.email',
                'a.provincia',
                'a.citta',
                'a.indirizzo',
                'a.active',
                'a.deleted_at',
                DB::raw('MIN(u.id) as supervisor_user_id'),
                DB::raw('uc.username as created_by_name'),
                DB::raw('uu.username as updated_by_name')
            )
            ->whereNull('a.deleted_at')
            ->groupBy(
                'a.IdAssociazione',
                'a.Associazione',
                'a.email',
                'a.provincia',
                'a.citta',
                'a.indirizzo',
                'a.active',
                'a.deleted_at',
                'uc.username',
                'uu.username'
            );

        if ($val = $request->input('search.value')) {
            $base->where(function ($q) use ($val) {
                $q->where('a.Associazione', 'like', "%{$val}%")
                    ->orWhere('a.email', 'like', "%{$val}%")
                    ->orWhere('a.provincia', 'like', "%{$val}%")
                    ->orWhere('a.citta', 'like', "%{$val}%");
            });
        }

        $total    = DB::table(self::TABLE)->whereNull('deleted_at')->count();
        $filtered = (clone $base)->count();

        if ($order = $request->input('order.0')) {
            $col = $request->input("columns.{$order['column']}.data");
            $dir = $order['dir'];
            $base->orderBy($col, $dir);
        }

        $start  = max(0, (int)$request->input('start', 0));
        $length = max(1, (int)$request->input('length', 10));
        $data   = $base->skip($start)->take($length)->get();

        return [
            'draw'            => (int)$request->input('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ];
    }

    /**
     * Crea una nuova associazione e ritorna l'ID inserito
     */
    public static function createAssociation(array $data): int {
        $now = Carbon::now();

        $payload = [
            'Associazione' => $data['Associazione'],
            'email'        => $data['email'],
            'provincia'    => $data['provincia'],
            'citta'        => $data['citta'],
            'indirizzo'    => $data['indirizzo'],
            'note'         => $data['note'] ?? null,
            'active'       => $data['active'] ?? true,
            'created_by'   => $data['created_by'] ?? null,
            'updated_by'   => $data['updated_by'] ?? null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];

        $id = DB::table(self::TABLE)->insertGetId($payload);

        if (! $id) {
            throw new \Exception("Insert su tabella " . self::TABLE . " fallita.");
        }

        return $id;
    }


    /**
     * Toggle dello stato active (usa IdAssociazione!)
     */
    public static function toggleActive(int $id) {
        $row = DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->first();

        if (! $row) return;

        DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->update(['active' => $row->active ? 0 : 1]);
    }

    /**
     * Soft delete via deleted_at
     */
    public static function softDelete(int $id) {
        DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->update(['deleted_at' => Carbon::now()]);
    }

    public static function getById(int $idAssociazione) {
        return DB::table(self::TABLE)
            ->where('idAssociazione', $idAssociazione)
            ->first();
    }

    public static function findById(int $id) {
        return DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->first();
    }

    public static function getAdminUserFor(int $idAssociazione) {
        return DB::table('users')
            ->where('IdAssociazione', $idAssociazione)
            ->whereIn('role_id', [1, 2, 3]) // Admin/SuperAdmin/Supervisor
            ->first();
    }
}

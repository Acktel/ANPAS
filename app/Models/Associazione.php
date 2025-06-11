<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Associazione
{
    protected const TABLE = 'associazioni';

    /**
     * Restituisce i dati per DataTables, includendo anche l'ID del Supervisor/Admin/SuperAdmin
     */
    public static function getAll($request)
    {
        // 1) Query base: left join con users per recuperare chi ha role_id 1, 2 o 3
        $base = DB::table(self::TABLE . ' as a')
            ->leftJoin('users as u', function($join) {
                $join->on('a.IdAssociazione', '=', 'u.IdAssociazione');
            })
            ->select(
                'a.IdAssociazione',
                'a.Associazione',
                'a.email',
                'a.provincia',
                'a.citta',
                'a.active',
                'a.deleted_at',
                DB::raw('MIN(u.id) as supervisor_user_id')
            )
            ->whereNull('a.deleted_at')
            ->groupBy(
                'a.IdAssociazione',
                'a.Associazione',
                'a.email',
                'a.provincia',
                'a.citta',
                'a.active',
                'a.deleted_at'
            );

        // 2) Filtro ricerca
        if ($val = $request->input('search.value')) {
            $base->where(function($q) use ($val) {
                $q->where('a.Associazione', 'like', "%{$val}%")
                  ->orWhere('a.email', 'like', "%{$val}%")
                  ->orWhere('a.provincia', 'like', "%{$val}%")
                  ->orWhere('a.citta', 'like', "%{$val}%");
            });
        }

        // 3) Conteggi (escludendo i soft‐deleted)
        $total    = DB::table(self::TABLE)->whereNull('deleted_at')->count();
        $filtered = (clone $base)->count();

        // 4) Ordinamento
        if ($order = $request->input('order.0')) {
            $col = $request->input("columns.{$order['column']}.data");
            $dir = $order['dir'];
            $base->orderBy($col, $dir);
        }

        // 5) Paginazione
        $start  = max(0, (int)$request->input('start', 0));
        $length = max(1, (int)$request->input('length', 10));
        $data   = $base->skip($start)->take($length)->get();

        // 6) Ritorna i dati per DataTables
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
    public static function createAssociation(array $data): int
    {
        $now = Carbon::now();

        $payload = [
            'Associazione' => $data['Associazione'],
            'email'        => $data['email'],
            'provincia'    => $data['provincia'],
            'citta'        => $data['citta'],
            'active'       => $data['active'] ?? true,
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
    public static function toggleActive(int $id)
    {
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
    public static function softDelete(int $id)
    {
        DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->update(['deleted_at' => Carbon::now()]);
    }

    public static function getById(int $idAssociazione)
    {
        return DB::table(self::TABLE)
            ->where('idAssociazione', $idAssociazione)
            ->first();
    }
}

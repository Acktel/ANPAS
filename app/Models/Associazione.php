<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class Associazione
{
    /** Nome tabella */
    public const TABLE = 'associazioni';

    /**
     * Ritorna i dati per DataTables.
     * - include cap
     * - include username creato/aggiornato
     * - include (se esiste) un id utente con ruolo elevato per “impersonate”
     */
    public static function getAll(Request $request): array
    {
        // id utenti con ruoli elevati (adatta se i tuoi id ruoli sono diversi)
        $elevatedRoleIds = [1, 2, 3]; // Admin, SuperAdmin, Supervisor

        $base = DB::table(self::TABLE . ' as a')
            // utente supervisor/admin: prendi MIN per avere 1 id stabile
            ->leftJoin('users as u', function ($join) {
                $join->on('a.IdAssociazione', '=', 'u.IdAssociazione');
            })
            ->leftJoin('users as uc', 'a.created_by', '=', 'uc.id')
            ->leftJoin('users as uu', 'a.updated_by', '=', 'uu.id')
            ->selectRaw("
                a.IdAssociazione,
                a.Associazione,
                a.email,
                a.provincia,
                a.citta,
                a.cap,
                a.indirizzo,
                a.active,
                a.deleted_at,
                MIN(CASE WHEN u.role_id IN (" . implode(',', $elevatedRoleIds) . ") THEN u.id END) as supervisor_user_id,
                uc.username as created_by_name,
                uu.username as updated_by_name
            ")
            ->whereNull('a.deleted_at')
            ->groupBy(
                'a.IdAssociazione',
                'a.Associazione',
                'a.email',
                'a.provincia',
                'a.citta',
                'a.cap',
                'a.indirizzo',
                'a.active',
                'a.deleted_at',
                'uc.username',
                'uu.username'
            );

        // ricerca fulltext base
        if ($val = trim((string) $request->input('search.value'))) {
            $base->where(function ($q) use ($val) {
                $q->where('a.Associazione', 'like', "%{$val}%")
                  ->orWhere('a.email', 'like', "%{$val}%")
                  ->orWhere('a.provincia', 'like', "%{$val}%")
                  ->orWhere('a.citta', 'like', "%{$val}%")
                  ->orWhere('a.cap', 'like', "%{$val}%")
                  ->orWhere('a.indirizzo', 'like', "%{$val}%");
            });
        }

        // totali
        $total = DB::table(self::TABLE)->whereNull('deleted_at')->count();

        // ATTENZIONE: con groupBy un count “semplice” può essere fuorviante.
        // Qui facciamo il count sulla lista già raggruppata.
        $filtered = (clone $base)->get()->count();

        // ordering: whitelist colonne ordinate
        $orderable = [
            'Associazione' => 'a.Associazione',
            'email'        => 'a.email',
            'provincia'    => 'a.provincia',
            'citta'        => 'a.citta',
            'cap'          => 'a.cap',
            'indirizzo'    => 'a.indirizzo',
            'updated_by_name' => 'uu.username',
        ];

        if ($order = $request->input('order.0')) {
            $colKey = $request->input("columns.{$order['column']}.data");
            $dir    = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

            if (isset($orderable[$colKey])) {
                $base->orderBy($orderable[$colKey], $dir);
            } else {
                $base->orderBy('a.Associazione'); // fallback
            }
        } else {
            $base->orderBy('a.Associazione');
        }

        // paginazione
        $start  = max(0, (int) $request->input('start', 0));
        $length = max(1, (int) $request->input('length', 10));
        $data   = $base->skip($start)->take($length)->get();

        return [
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ];
    }

    /**
     * Crea una associazione e restituisce l'ID.
     */
    public static function createAssociation(array $data): int
    {
        $now = Carbon::now();

        $payload = [
            'Associazione' => $data['Associazione'],
            'email'        => $data['email'],
            'provincia'    => $data['provincia'],
            'citta'        => $data['citta'],
            'cap'          => $data['cap'] ?? null,
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
            throw new \RuntimeException('Insert su tabella ' . self::TABLE . ' fallita.');
        }

        return $id;
    }

    /**
     * Aggiorna una associazione per IdAssociazione.
     */
    public static function updateAssociation(int $id, array $data): void
    {
        $payload = [
            'Associazione' => $data['Associazione'],
            'email'        => $data['email'],
            'provincia'    => $data['provincia'],
            'citta'        => $data['citta'],
            'cap'          => $data['cap'] ?? null,
            'indirizzo'    => $data['indirizzo'],
            'note'         => $data['note'] ?? null,
            'updated_by'   => $data['updated_by'] ?? null,
            'updated_at'   => Carbon::now(),
        ];

        DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->update($payload);
    }

    /**
     * Toggle campo active.
     */
    public static function toggleActive(int $id): void
    {
        $row = DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->first();

        if (! $row) {
            return;
        }

        DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->update([
                'active'     => $row->active ? 0 : 1,
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Soft delete (imposta deleted_at).
     */
    public static function softDelete(int $id): void
    {
        DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->update(['deleted_at' => Carbon::now()]);
    }

    /**
     * Recupera per IdAssociazione (attenzione al nome colonna).
     */
    public static function getById(int $id)
    {
        return DB::table(self::TABLE)
            ->where('IdAssociazione', $id)
            ->first();
    }

    /**
     * Alias storico.
     */
    public static function findById(int $id)
    {
        return self::getById($id);
    }

    /**
     * Ritorna un utente “admin/supervisor/superadmin” collegato all’associazione (se esiste).
     * Adatta la lista dei role_id se necessario.
     */
    public static function getAdminUserFor(int $idAssociazione)
    {
        $elevatedRoleIds = [1, 2, 3];

        return DB::table('users')
            ->where('IdAssociazione', $idAssociazione)
            ->whereIn('role_id', $elevatedRoleIds)
            ->orderBy('id') // uno stabile
            ->first();
    }
}

<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * Attributi mass assignable
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'email',
        'password',
        'role_id',
        'active',
        'IdAssociazione',
        'last_login_at',
    ];

    /**
     * Attributi nascosti nelle serializzazioni
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting automatici
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'active'            => 'boolean',
        'last_login_at'     => 'datetime',
    ];

    /**
     * Recupera dati utente per DataTables, filtrati lato server.
     * Supporta filtro per associazione selezionata.
     * Restituisce array con chiavi: draw, recordsTotal, recordsFiltered, data.
     *
     * @param  Request  $request
     * @return array
     */
    public static function getDataTableForAdmin(Request $request)
    {
        $assocId = $request->input('idAssociazione');

        // 1) Query di base con join associazioni
        $base = DB::table('users as u')
            ->select([
                'u.id',
                'u.firstname',
                'u.lastname',
                'u.username',
                'u.email',
                'u.active',
                'u.created_at',
                'a.Associazione as association_name',
            ])
            ->leftJoin('associazioni as a', 'u.IdAssociazione', '=', 'a.IdAssociazione')
            ->whereNull('a.deleted_at')
            ->where('a.IdAssociazione', '!=', 1);

        // 2) Filtro per associazione selezionata
        if ($assocId) {
            $base->where('u.IdAssociazione', $assocId);
        }

        // 3) Search globale di DataTables
        if ($search = $request->input('search.value')) {
            $base->where(function ($q) use ($search) {
                $q->where('u.firstname', 'like', "%{$search}%")
                  ->orWhere('u.lastname', 'like', "%{$search}%")
                  ->orWhere('u.username', 'like', "%{$search}%")
                  ->orWhere('u.email', 'like', "%{$search}%")
                  ->orWhere('a.Associazione', 'like', "%{$search}%");
            });
        }

        // 4) Conteggio record totali e filtrati
        $total    = DB::table('users')->count();
        $filtered = (clone $base)->count();

        // 5) Ordinamento dinamico
        if ($order = $request->input('order.0')) {
            $col = $request->input("columns.{$order['column']}.data");
            $dir = $order['dir'];
            $base->orderBy($col, $dir);
        }

        // 6) Paginazione
        $start  = max(0, (int)$request->input('start', 0));
        $length = max(1, (int)$request->input('length', 10));
        $data   = $base->skip($start)->take($length)->get();

        // 7) Risposta formattata per DataTables
        return [
            'draw'            => (int)$request->input('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ];
    }

    /**
     * Controllo ruolo SuperAdmin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('SuperAdmin');
    }

    /**
     * Controllo ruolo Admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }
}

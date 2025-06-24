<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable {
    use HasFactory, Notifiable, HasRoles;

    /**
     * Attributi che si possono assegnare in massa
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
    ];

    /**
     * Attributi da nascondere nelle serializzazioni (es. JSON)
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casting automatici di alcuni campi
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'active'            => 'boolean',
    ];

    /**
     * Recupera tutti gli utenti (per Admin/Supervisor) in formato DataTable.
     * Restituisce un array con chiavi: draw, recordsTotal, recordsFiltered, data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public static function getDataTableForAdmin($request) {
        // 1) Query base: selezioniamo utenti e join con associazioni
        $base = DB::table('users as u')
            ->select(
                'u.id',
                'u.firstname',
                'u.lastname',
                'u.username',
                'u.email',
                'u.active',
                'u.created_at',
                'a.Associazione as association_name'
            )
            ->leftJoin('associazioni as a', 'u.IdAssociazione', '=', 'a.IdAssociazione');

        // 2) Filtraggio (search di DataTables)
        if ($val = $request->input('search.value')) {
            $base->where(function ($q) use ($val) {
                $q->where('u.firstname', 'like', "%{$val}%")
                    ->orWhere('u.lastname', 'like', "%{$val}%")
                    ->orWhere('u.email', 'like', "%{$val}%")
                    ->orWhere('u.username', 'like', "%{$val}%")
                    ->orWhere('a.Associazione', 'like', "%{$val}%");
            });
        }

        // 3) Conteggi (totale + filtrati)
        $total    = DB::table('users')->count();
        $filtered = (clone $base)->count();

        // 4) Ordinamento (se specificato da DataTables)
        if ($order = $request->input('order.0')) {
            $col = $request->input("columns.{$order['column']}.data");
            $dir = $order['dir'];
            $base->orderBy($col, $dir);
        }

        // 5) Paginazione (start, length)
        $start  = max(0, (int)$request->input('start', 0));
        $length = max(1, (int)$request->input('length', 10));
        $data   = $base->skip($start)->take($length)->get();

        // 6) Restituiamo l’array formattato per DataTables
        return [
            'draw'            => (int)$request->input('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ];
    }

    public function isSuperAdmin(): bool {
        return $this->hasRole('SuperAdmin');
    }

    public function isAdmin(): bool {
        return $this->hasRole('Admin');
    }
}

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
     * Normalizza l'email sempre in lowercase/trim.
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = mb_strtolower(trim((string) $value));
    }

    /**
     * Data provider per DataTables lato admin.
     * - Filtra per idAssociazione se passato
     * - Ricerca case-insensitive su firstname, lastname, email, associazione
     * - Ordinamento con whitelist
     * - Conteggi total/filtered coerenti
     */
    public static function getDataTableForAdmin(Request $request): array
    {
        $assocId = $request->input('idAssociazione');

        // Base query (senza ricerca), con join associazione e filtro GOD escluso
        $base = DB::table('users as u')
            ->leftJoin('associazioni as a', 'u.IdAssociazione', '=', 'a.IdAssociazione')
            ->whereNull('a.deleted_at')
            ->where('a.IdAssociazione', '!=', 1);

        if (!empty($assocId)) {
            $base->where('u.IdAssociazione', (int) $assocId);
        }

        // total = conteggio senza ricerca
        $total = (clone $base)->count();

        // Ricerca globale (case-insensitive)
        if ($search = trim((string) $request->input('search.value'))) {
            $s = mb_strtolower($search);
            $base->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(u.firstname)    LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(u.lastname)     LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(u.email)        LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(a.Associazione) LIKE ?', ["%{$s}%"]);
            });
        }

        // filtered = conteggio con ricerca
        $filtered = (clone $base)->count();

        // Selezione delle colonne da restituire
        $base->select([
            'u.id',
            'u.firstname',
            'u.lastname',
            // 'u.username', // intenzionalmente NON esposto
            'u.email',
            'u.active',
            'u.created_at',
            DB::raw('a.Associazione as association_name'),
        ]);

        // Ordinamento (whitelist)
        $orderable = [
            'firstname'        => 'u.firstname',
            'lastname'         => 'u.lastname',
            'email'            => 'u.email',
            'association_name' => 'a.Associazione',
            'active'           => 'u.active',
            'created_at'       => 'u.created_at',
        ];

        $order = $request->input('order.0');
        if (is_array($order)) {
            $colIndex = $order['column'] ?? null;
            $dir      = strtolower($order['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            if ($colIndex !== null) {
                $colKey = $request->input("columns.$colIndex.data");
                $dbCol  = $orderable[$colKey] ?? null;
                if ($dbCol) {
                    $base->orderBy($dbCol, $dir);
                } else {
                    $base->orderBy('u.lastname')->orderBy('u.firstname');
                }
            }
        } else {
            $base->orderBy('u.lastname')->orderBy('u.firstname');
        }

        // Paging
        $start  = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        if ($length < 1)   $length = 10;
        if ($length > 1000) $length = 1000;

        $data = $base->skip($start)->take($length)->get();

        return [
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('SuperAdmin');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('Admin');
    }
}

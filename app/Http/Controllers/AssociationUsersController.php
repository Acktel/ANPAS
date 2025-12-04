<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssociationUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Solo Supervisor/Admin/SuperAdmin (come definito nel Gate impersonate-users)
        $this->middleware('can:impersonate-users');
    }

    /**
     * GET /my-users
     * Mostra la pagina con DataTable degli utenti nella stessa associazione
     */
    public function index()
    {
        return view('associazioni.users_index');
    }

    /**
     * GET /my-users/data
     * JSON per DataTables, filtrato sugli utenti della stessa associazione
     */
    public function getData(Request $request)
    {
        $assocId = auth()->user()->IdAssociazione;

        $base = DB::table('users as u')
            ->select([
                'u.id',
                'u.firstname',
                'u.lastname',
                'u.email',
                'u.active',
                'u.created_at',
            ])
            ->where('u.IdAssociazione', $assocId);

        // Search (case-insensitive)
        if ($search = trim((string) $request->input('search.value'))) {
            $s = mb_strtolower($search);
            $base->where(function ($q) use ($s) {
                $q->whereRaw('LOWER(u.firstname) LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(u.lastname)  LIKE ?', ["%{$s}%"])
                  ->orWhereRaw('LOWER(u.email)     LIKE ?', ["%{$s}%"]);
            });
        }

        // Conteggi
        $total    = DB::table('users')->where('IdAssociazione', $assocId)->count();
        $filtered = (clone $base)->count();

        // Ordinamento (whitelist)
        $orderable = [
            'firstname'  => 'u.firstname',
            'lastname'   => 'u.lastname',
            'email'      => 'u.email',
            'active'     => 'u.active',
            'created_at' => 'u.created_at',
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
        if ($length > 500) $length = 500;

        $data = $base->skip($start)->take($length)->get();

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }

    /**
     * GET /my-users/create
     * Mostra il form per creare un nuovo utente nell'associazione
     */
    public function create()
    {
        return view('associazioni.users_create');
    }

    /**
     * POST /my-users
     * Crea un nuovo utente legato all'associazione corrente (email+password; setta role_id e ruolo Spatie)
     */
    public function store(Request $request)
    {
        $assocId = auth()->user()->IdAssociazione;

        // normalizza email prima della validate
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname'  => 'nullable|string|max:255',
            'email'     => 'required|email:rfc,dns|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required_with:password|string|min:8',
        ], [
            'email.unique'       => 'Questa email è già in uso.',
            'password.confirmed' => 'La password e la conferma non coincidono.',
            'password.min'       => 'La password deve avere almeno 8 caratteri.',
        ]);

        $now = now();

        // ruolo base = User (sia Spatie sia role_id)
        $roleName = 'User';
        $roleId   = Role::where('name', $roleName)->value('id');

        $userId = DB::table('users')->insertGetId([
            'firstname'       => $data['firstname'],
            'lastname'        => $data['lastname'] ?? '',
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'role_id'         => $roleId,           // 👈 allineo role_id
            'active'          => true,
            'IdAssociazione'  => $assocId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // assegna anche il ruolo Spatie
        $userModel = User::find($userId);
        if ($userModel && $roleId) {
            $userModel->assignRole($roleName);
        }

        return redirect()
            ->route('my-users.index')
            ->with('success', 'Nuovo utente creato con successo!');
    }

    /**
     * DELETE /my-users/{id}
     * Elimina un utente della stessa associazione (impedisci self-delete)
     */
    public function destroy($id)
    {
        $currentUserId = auth()->id();

        $user = DB::table('users')
            ->where('id', $id)
            ->where('IdAssociazione', auth()->user()->IdAssociazione)
            ->first();

        if (! $user) {
            return back()->withErrors(['error' => 'Operazione non consentita.']);
        }

        if ((int) $user->id === (int) $currentUserId) {
            return back()->withErrors(['error' => 'Non puoi eliminare te stesso.']);
        }

        DB::table('users')->where('id', $id)->delete();

        return back()->with('success', 'Utente eliminato.');
    }

    /**
     * GET /my-users/{id}/edit
     * Mostra il form di modifica per un utente della stessa associazione
     */
    public function edit($id)
    {
        $user = DB::table('users')
            ->where('id', $id)
            ->where('IdAssociazione', auth()->user()->IdAssociazione)
            ->first();

        if (! $user) {
            abort(403, 'Utente non trovato o non appartenente alla tua associazione.');
        }

        return view('associazioni.users_edit', compact('user'));
    }

    /**
     * PUT /my-users/{id}
     * Aggiorna un utente della propria associazione (email unica; password opzionale)
     */
    public function update(Request $request, $id)
    {
        $exists = DB::table('users')
            ->where('id', $id)
            ->where('IdAssociazione', auth()->user()->IdAssociazione)
            ->exists();

        if (! $exists) {
            abort(403, 'Utente non autorizzato.');
        }

        // normalizza email prima della validate
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $rules = [
            'firstname' => 'required|string|max:255',
            'lastname'  => 'nullable|string|max:255',
            'email'     => 'required|email:rfc,dns|unique:users,email,' . $id,
            'password'              => 'nullable|string|min:8|confirmed',
            'password_confirmation' => 'nullable|string|min:8',
        ];

        $data = $request->validate($rules, [
            'email.unique'       => 'Questa email è già in uso.',
            'password.confirmed' => 'La password e la conferma non coincidono.',
            'password.min'       => 'La password deve avere almeno 8 caratteri.',
        ]);

        $update = [
            'firstname'  => $data['firstname'],
            'lastname'   => $data['lastname'] ?? '',
            'email'      => $data['email'],
            'updated_at' => now(),
        ];

        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        DB::table('users')->where('id', $id)->update($update);

        return redirect()
            ->route('my-users.index')
            ->with('success', 'Utente aggiornato correttamente!');
    }
}

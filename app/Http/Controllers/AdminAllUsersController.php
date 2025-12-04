<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Models\User;

class AdminAllUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:manage-all-associations');
    }

    /**
     * GET /all-users
     * Lista utenti con filtro per associazione (Admin/Supervisor).
     */
    public function index(Request $request)
    {
        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->where('IdAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        $sessionKey = 'associazione_selezionata';

        // Se arriva il filtro da query, aggiorno la sessione
        if ($request->filled('idAssociazione')) {
            session([$sessionKey => (int) $request->get('idAssociazione')]);
        }

        // Selezione: query || sessione || prima disponibile
        $selectedAssoc = (int) (
            $request->get('idAssociazione')
            ?? session($sessionKey)
            ?? ($associazioni->first()->IdAssociazione ?? 0)
        );

        return view('admin.all_users_index', compact('associazioni', 'selectedAssoc'));
    }

    /**
     * GET /all-users/data
     * JSON per DataTables, con fallback al filtro in sessione.
     */
    public function getData(Request $request)
    {
        $sessionKey = 'associazione_selezionata';

        if (!$request->filled('idAssociazione') && session()->has($sessionKey)) {
            $request->merge(['idAssociazione' => (int) session($sessionKey)]);
        }

        return User::getDataTableForAdmin($request);
    }

    /**
     * GET /all-users/create
     */
    public function create()
    {
        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->where('IdAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        $ruoli = Role::select('name')->orderBy('name')->get();

        $selectedAssoc = session('associazione_selezionata')
            ?? ($associazioni->first()->IdAssociazione ?? null);

        return view('admin.all_users_create', compact('associazioni', 'ruoli', 'selectedAssoc'));
    }

    /**
     * POST /all-users
     */
    public function store(Request $request)
    {
        // Normalizzo PRIMA di validare (evita falsi negativi su unique)
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'firstname'             => 'required|string|max:255',
            'lastname'              => 'nullable|string|max:255',
            // email non-bloccante: accetta qualsiasi stringa unica
            'email'                 => 'required|string|unique:users,email',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required_with:password|string|min:8',
            'IdAssociazione'        => 'required|integer|exists:associazioni,IdAssociazione',
            'role'                  => 'required|string|exists:roles,name',
            'note'                  => 'nullable|string',
        ], [
            'email.unique'          => 'Questa email è già in uso.',
            'password.confirmed'    => 'La password e la conferma non coincidono.',
            'password.min'          => 'La password deve avere almeno 8 caratteri.',
        ]);

        DB::beginTransaction();
        try {
            // sincronizzo anche role_id con Spatie
            $roleId = Role::where('name', $validated['role'])->value('id');

            $userId = DB::table('users')->insertGetId([
                'firstname'      => $validated['firstname'],
                'lastname'       => $validated['lastname'] ?? '',
                'email'          => $validated['email'],
                'password'       => Hash::make($validated['password']),
                'IdAssociazione' => (int) $validated['IdAssociazione'],
                'role_id'        => $roleId ?: null,
                'note'           => $validated['note'] ?? null,
                'active'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $user = User::findOrFail($userId);
            $user->assignRole($validated['role']);

            DB::commit();
            return redirect()->route('all-users.index')->with('success', 'Utente creato con successo!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()
                ->withErrors(['error' => 'Errore nella creazione: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /all-users/{id}/edit
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);

        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->where('IdAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        $ruoli = Role::select('name')->orderBy('name')->get();

        $selectedAssoc = session('associazione_selezionata')
            ?? ($associazioni->first()->IdAssociazione ?? null);

        // 👇 Questa è l’unica aggiunta facoltativa
        $hasPassword = !empty($user->password);

        return view('admin.all_users_edit', compact(
            'user', 'associazioni', 'ruoli', 'selectedAssoc', 'hasPassword'
        ));
    }


    /**
     * PUT /all-users/{id}
     */
    public function update(Request $request, $id)
    {
        // Normalizzo PRIMA di validare (coerenza con unique)
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'firstname'             => 'required|string|max:255',
            'lastname'              => 'nullable|string|max:255',

            // email non-bloccante: accetta qualsiasi stringa unica
            'email'                 => "required|string|unique:users,email,{$id}",

            'IdAssociazione'        => 'required|integer|exists:associazioni,IdAssociazione',
            'role'                  => 'required|string|exists:roles,name',
            'note'                  => 'nullable|string',

            // PASSWORD SOLO SE COMPILATA
            'password'              => 'nullable|min:8|confirmed',
            'password_confirmation' => 'nullable',

        ], [
            'email.unique'          => 'Questa email è già in uso.',
            'password.confirmed'    => 'La password e la conferma non coincidono.',
            'password.min'          => 'La password deve avere almeno 8 caratteri.',
        ]);


        DB::beginTransaction();
        try {
            $payload = [
                'firstname'      => $validated['firstname'],
                'lastname'       => $validated['lastname'] ?? '',
                'email'          => $validated['email'],
                'IdAssociazione' => (int) $validated['IdAssociazione'],
                'note'           => $validated['note'] ?? null,
                'updated_at'     => now(),
            ];

            if (!empty($validated['password'])) {
                $payload['password'] = Hash::make($validated['password']);
            }

            // sync role_id con ruolo scelto
            $roleId = Role::where('name', $validated['role'])->value('id');
            $payload['role_id'] = $roleId ?: null;

            DB::table('users')->where('id', $id)->update($payload);

            $user = User::findOrFail($id);
            $user->syncRoles([$validated['role']]);

            DB::commit();
            return redirect()->route('all-users.index')
                ->with('success', 'Utente aggiornato con successo!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()
                ->withErrors(['error' => 'Errore nell’aggiornamento: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /all-users/{id}
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);

            // Risposta JSON coerente con il fetch del frontend
            if (auth()->id() === $user->id) {
                return response()->json(['error' => 'Non puoi eliminare te stesso.'], 422);
            }

            $user->syncRoles([]);
            $user->delete();

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }
}

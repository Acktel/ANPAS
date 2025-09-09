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
     * Mostra la lista utenti con filtro per associazione (solo per ruoli elevati).
     */
    public function index(Request $request)
    {
        // Carica tutte le associazioni valide (escludi SuperAdmin)
        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->where('IdAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        if ($request->has('idAssociazione')) {
            // aggiorna la sessione se c’è una nuova selezione
            session(['selectedAssoc' => $request->get('idAssociazione')]);
        }

    
        $selectedAssoc = session('associazione_selezionata') ?? ($associazioni->first()->IdAssociazione ?? null);

        // Prendi il filtro dalla querystring o fallback alla prima
        // $selectedAssoc = $request->get('idAssociazione')
        //     ?? ($associazioni->first()->IdAssociazione ?? null);

        return view('admin.all_users_index', compact('associazioni', 'selectedAssoc'));
    }

    /**
     * GET /all-users/data
     * Restituisce JSON per DataTables, filtrato lato server.
     */
    public function getData(Request $request)
    {
        // Ritorna direttamente la risposta JSON prodotta dal model
        return User::getDataTableForAdmin($request);
    }

    /**
     * GET /all-users/create
     * Form di creazione utente
     */
    public function create()
    {
        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->where('IdAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();
    
        $ruoli = Role::select('name')
            ->orderBy('name')
            ->get();
    
        // Recupera dalla sessione il filtro selezionato
        $selectedAssoc = session('associazione_selezionata') ?? ($associazioni->first()->IdAssociazione ?? null);
    
        return view('admin.all_users_create', compact('associazioni', 'ruoli', 'selectedAssoc'));
    }

    /**
     * POST /all-users
     * Salva un nuovo utente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'firstname'      => 'required|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            'username'       => 'required|string|max:255|unique:users,username',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|string|min:8|confirmed',
            'IdAssociazione' => 'required|integer|exists:associazioni,IdAssociazione',
            'role'           => 'required|string|exists:roles,name',
            'note'           => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $userId = DB::table('users')->insertGetId([
                'firstname'      => $validated['firstname'],
                'lastname'       => $validated['lastname'] ?? '',
                'username'       => $validated['username'],
                'email'          => $validated['email'],
                'password'       => Hash::make($validated['password']),
                'IdAssociazione' => $validated['IdAssociazione'],
                'note'           => $validated['note'] ?? null,
                'active'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $user = User::findOrFail($userId);
            $user->assignRole($validated['role']);

            DB::commit();

            return redirect()->route('all-users.index')
                ->with('success', 'Utente creato con successo!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Errore nella creazione: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /all-users/{id}/edit
     * Form di modifica utente
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);

        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->where('IdAssociazione', '!=', 1)
            ->orderBy('Associazione')
            ->get();

        $ruoli = Role::select('name')
            ->orderBy('name')
            ->get();

        // Recupera dalla sessione il filtro selezionato
        $selectedAssoc = session('associazione_selezionata') ?? ($associazioni->first()->IdAssociazione ?? null);

        return view('admin.all_users_edit', compact('user', 'associazioni', 'ruoli', 'selectedAssoc'));
    }

    /**
     * PUT /all-users/{id}
     * Aggiorna i dati utente
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'firstname'      => 'required|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            'username'       => "required|string|max:255|unique:users,username,{$id}",
            'email'          => "required|email|unique:users,email,{$id}",
            'IdAssociazione' => 'required|integer|exists:associazioni,IdAssociazione',
            'role'           => 'required|string|exists:roles,name',
            'note'           => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            DB::table('users')->where('id', $id)->update([
                'firstname'      => $validated['firstname'],
                'lastname'       => $validated['lastname'] ?? '',
                'username'       => $validated['username'],
                'email'          => $validated['email'],
                'IdAssociazione' => $validated['IdAssociazione'],
                'note'           => $validated['note'] ?? null,
                'updated_at'     => now(),
            ]);

            $user = User::findOrFail($id);
            $user->syncRoles([$validated['role']]);

            DB::commit();

            return redirect()->route('all-users.index')
                ->with('success', 'Utente aggiornato con successo!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Errore nell\'aggiornamento: ' . $e->getMessage()]);
        }
    }

    /**
     * DELETE /all-users/{id}
     * Elimina un utente
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            // Non puoi eliminare te stesso
            if (auth()->id() === $user->id) {
                return back()->withErrors(['error' => 'Non puoi eliminare te stesso.']);
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

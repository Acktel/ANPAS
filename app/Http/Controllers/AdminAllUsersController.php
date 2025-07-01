<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Models\Associazione;

class AdminAllUsersController extends Controller {
    public function __construct() {
        $this->middleware('auth');
        $this->middleware('can:manage-all-associations');
    }

    /** GET /all-users */
    public function index() {
        return view('admin.all_users_index');
    }

    /** GET /all-users/data */
    public function getData(Request $request) {
        $response = User::getDataTableForAdmin($request);
        return response()->json($response);
    }

    /** GET /all-users/create */
    public function create() {
        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        $ruoli = Role::select('name')->orderBy('name')->get();

        return view('admin.all_users_create', compact('associazioni', 'ruoli'));
    }

    /** POST /all-users */
    public function store(Request $request) {
        $validated = $request->validate([
            'firstname'      => 'required|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            'username'       => 'required|string|max:255|unique:users,username',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|string|min:8|confirmed',
            'IdAssociazione' => 'required|integer|exists:associazioni,IdAssociazione',
            'role'           => 'required|string|exists:roles,name',
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
    /** GET /all-users/{id}/edit */
    public function edit($id) {
        $user = User::findOrFail($id);
        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        $ruoli = Role::select('name')->orderBy('name')->get();

        return view('admin.all_users_edit', compact('user', 'associazioni', 'ruoli'));
    }

    /** PUT /all-users/{id} */
    public function update(Request $request, $id) {
        $validated = $request->validate([
            'firstname'      => 'required|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            'username'       => "required|string|max:255|unique:users,username,{$id}",
            'email'          => "required|email|unique:users,email,{$id}",
            'IdAssociazione' => 'required|integer|exists:associazioni,IdAssociazione',
            'role'           => 'required|string|exists:roles,name',
        ]);

        DB::beginTransaction();

        try {
            DB::table('users')->where('id', $id)->update([
                'firstname'      => $validated['firstname'],
                'lastname'       => $validated['lastname'] ?? '',
                'username'       => $validated['username'],
                'email'          => $validated['email'],
                'IdAssociazione' => $validated['IdAssociazione'],
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

    public function destroy($id) {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);

            // Non puoi eliminare te stesso
            if (auth()->id() === $user->id) {
                return back()->withErrors(['error' => 'Non puoi eliminare te stesso.']);
            }

            $user->syncRoles([]); // Rimuove i ruoli
            $user->delete();

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Errore: ' . $e->getMessage()], 500);
        }
    }
}

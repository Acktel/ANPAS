<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Models\Associazione;

class AdminAllUsersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:manage-all-associations');
    }

    /** GET /all-users */
    public function index()
    {
        return view('admin.all_users_index');
    }

    /** GET /all-users/data */
    public function getData(Request $request)
    {
        $response = User::getDataTableForAdmin($request);
        return response()->json($response);
    }

    /** GET /all-users/create */
    public function create()
    {
        $associazioni = DB::table('associazioni')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        $ruoli = Role::select('name')->orderBy('name')->get();

        return view('admin.all_users_create', compact('associazioni', 'ruoli'));
    }

    /** POST /all-users */
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
}

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
        // Solo chi è Supervisor o Admin può accedere a queste rotte
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
     * Restituisce JSON per DataTables, filtrato solo sugli utenti della stessa associazione 
     */
    public function getData(Request $request)
    {
        $assocId = auth()->user()->IdAssociazione;

        // Base query: prendo solo gli utenti con stesso IdAssociazione e non cancellati
        $base = DB::table('users')
            ->select('id', 'firstname', 'lastname', 'username', 'email', 'active', 'created_at')
            ->where('IdAssociazione', $assocId);

        // Filtro ricerca
        if ($val = $request->input('search.value')) {
            $base->where(function($q) use ($val) {
                $q->where('firstname', 'like', "%{$val}%")
                  ->orWhere('lastname', 'like', "%{$val}%")
                  ->orWhere('email', 'like', "%{$val}%");
            });
        }

        // Conteggi
        $total    = DB::table('users')->where('IdAssociazione', $assocId)->count();
        $filtered = (clone $base)->count();

        // Ordinamento
        if ($order = $request->input('order.0')) {
            $col = $request->input("columns.{$order['column']}.data");
            $dir = $order['dir'];
            $base->orderBy($col, $dir);
        }

        // Paginazione
        $start  = max(0, (int)$request->input('start', 0));
        $length = max(1, (int)$request->input('length', 10));
        $data   = $base->skip($start)->take($length)->get();

        return response()->json([
            'draw'            => (int)$request->input('draw', 1),
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
     * Salva il nuovo utente legato all'associazione corrente
     */
    public function store(Request $request)
    {
        $assocId = auth()->user()->IdAssociazione;

        $data = $request->validate([
            'firstname' => 'required|string|max:255',
            'lastname'  => 'nullable|string|max:255',
            'username'  => 'required|string|max:255|unique:users,username',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            // puoi aggiungere altre regole se serve
        ]);

        // Creo l'utente usando Query Builder
        $now = now();
        $userId = DB::table('users')->insertGetId([
            'firstname'       => $data['firstname'],
            'lastname'        => $data['lastname'] ?? '',
            'username'        => $data['username'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'role_id'         => Role::where('name', 'User')->first()->id, 
            // o imposta qual è il ruolo “base” per un utente generico
            'active'          => true,
            'IdAssociazione'  => $assocId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // Se vuoi assegnare ruoli specifici (ad es “User”),  
        // puoi fare $userModel = User::find($userId); $userModel->assignRole('User');

        return redirect()
            ->route('my-users.index')
            ->with('success', 'Nuovo utente creato con successo!');
    }

    /**
     * DELETE /my-users/{id}
     * Elimina (o soft-delete) un utente dell'associazione (opzionale da implementare)
     */
    public function destroy($id)
    {
        // Assicurati che l'utente appartenga alla stessa associazione
        $user = DB::table('users')
            ->where('id', $id)
            ->where('IdAssociazione', auth()->user()->IdAssociazione)
            ->first();

        if ($user) {
            // Per semplicità, facciamo un delete “hard”
            DB::table('users')->where('id', $id)->delete();
            return back()->with('success', 'Utente eliminato.');
        }

        return back()->withErrors(['error' => 'Operazione non consentita.']);
    }
}

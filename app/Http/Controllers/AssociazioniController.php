<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Models\Associazione;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use App\Mail\AdminUserInvite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssociazioniController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    /** GET /associazioni */
    public function index() {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        Log::info("Entrato in AssociazioniController@index");

        $associazioni = collect(Associazione::getAll(request()));

        return view('associazioni.index', compact('associazioni', 'anno'));
    }

    /** GET /associazioni/data */
    public function getData(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isSuper = $user->hasRole('SuperAdmin');

        $result = Associazione::getAll($request, $anno);

        if (! $isSuper) {
            $rows = $result['data'];
            $filtered = $rows->filter(function ($row) {
                return $row->Associazione !== 'Associazione GOD' && $row->Associazione !== 'GOD';
            });

            $result['data'] = $filtered->values();
            $result['recordsFiltered'] = $filtered->count();
        }

        return response()->json($result);
    }

    /** POST /associazioni */
    public function store(Request $request) {
        $validated = $request->validate([
            'Associazione'      => 'required|string|max:255',
            'email'             => 'required|email|unique:associazioni,email',
            'provincia'         => 'required|string|max:100',
            'citta'             => 'required|string|max:100',
            'indirizzo'         => 'required|string|max:255',
            'note'              => 'nullable|string',
            'adminuser_name'    => 'required|string|max:255',
            'adminuser_email'   => 'required|email|unique:users,email',
        ]);

        // 1) crea l’associazione
        $now = now();
        $userId = auth()->id();

        $associazioneId = DB::table('associazioni')->insertGetId([
            'Associazione' => $validated['Associazione'],
            'email'        => $validated['email'],
            'provincia'    => $validated['provincia'],
            'citta'        => $validated['citta'],
            'indirizzo'    => $validated['indirizzo'],
            'note'         => $validated['note'] ?? null,
            'active'       => true,
            'created_by'   => $userId,
            'updated_by'   => $userId,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // 2) assegna ruolo
        $isFirst = Associazione::count() === 1;
        $roleName = $isFirst ? 'AdminUser' : 'User';
        $role = Role::where('name', $roleName)->firstOrFail();

        // 3) crea utente admin
        $password = Str::random(10);
        $user = User::create([
            'firstname'       => '',
            'lastname'        => '',
            'username'        => $validated['adminuser_name'],
            'email'           => $validated['adminuser_email'],
            'password'        => Hash::make($password),
            'role_id'         => $role->id,
            'active'          => true,
            'IdAssociazione'  => $associazioneId,
        ]);

        $user->assignRole($role);

        // 4) invia email reset password
        $token = Password::createToken($user);
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));
        Mail::to($user)->send(new AdminUserInvite($user, $resetUrl));

        return redirect()
            ->route('dashboard')
            ->with('success', "Associazione e utente creati. Controlla la mail di {$user->email}.");
    }

    /** POST /associazioni/{id}/toggle-active */
    public function toggleActive($id) {
        Associazione::toggleActive($id);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Stato attivazione dell\'associazione modificato!');
    }

    /** DELETE /associazioni/{id} */
    public function destroy($id) {
        Associazione::softDelete($id);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Associazione eliminata!');
    }

    public function create() {
        return view('associazioni.create');
    }

    public function edit($id) {
        $associazione = Associazione::findById($id);

        if (! $associazione) {
            abort(404, 'Associazione non trovata');
        }

        $adminUser = Associazione::getAdminUserFor($id);

        return view('associazioni.edit', compact('associazione', 'adminUser'));
    }

    public function update(Request $request, $id) {
        $data = $request->validate([
            'Associazione' => 'required|string|max:255',
            'email'        => 'required|email',
            'provincia'    => 'required|string|max:100',
            'citta'        => 'required|string|max:100',
            'indirizzo'    => 'required|string|max:255',
        ]);

        DB::table('associazioni')
            ->where('IdAssociazione', $id)
            ->update([
                'Associazione' => $data['Associazione'],
                'email'        => $data['email'],
                'provincia'    => $data['provincia'],
                'citta'        => $data['citta'],
                'indirizzo'    => $data['indirizzo'],
                'updated_by'   => auth()->id(),
                'updated_at'   => now(),
            ]);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Associazione aggiornata con successo.');
    }
}

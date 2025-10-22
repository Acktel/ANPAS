<?php

namespace App\Http\Controllers;

use App\Mail\SupervisorInvite;
use App\Models\Associazione;
use App\Models\User;
use App\Models\Cities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AssociazioniController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** =========================
     *  GET /associazioni (index)
     *  ========================= */
    public function index(Request $request)
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        $associazioni  = collect();
        $selectedAssoc = null;

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $associazioni = DB::table('associazioni')
                ->select('IdAssociazione', 'Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();

            // se arriva via GET lo salvo anche in session
            $selectedAssoc = $request->get('idAssociazione')
                ?? ($associazioni->first()->IdAssociazione ?? null);
        } else {
            // utenti standard: solo la propria associazione
            $selectedAssoc = (int) $user->IdAssociazione;
        }

        if ($request->filled('idAssociazione')) {
            session(['associazione_selezionata' => (int) $request->idAssociazione]);
        }
        $selectedAssoc = session('associazione_selezionata') ?? $selectedAssoc;

        return view('associazioni.index', compact('anno', 'associazioni', 'selectedAssoc'));
    }

    /** =========================
     *  GET /associazioni/data  (DataTables)
     *  ========================= */
    public function getData(Request $request)
    {
        $user    = Auth::user();
        $isSuper = $user->hasRole('SuperAdmin');

        $result = Associazione::getAll($request);

        // nascondi “GOD” ai non-super
        if (! $isSuper) {
            $rows = collect($result['data']);
            $filtered = $rows->filter(function ($row) {
                return $row->Associazione !== 'Associazione GOD' && $row->Associazione !== 'GOD';
            });

            $result['data']            = $filtered->values();
            $result['recordsFiltered'] = $filtered->count();
        }

        return response()->json($result);
    }

    /** =========================
     *  POST /associazioni  (store)
     *  ========================= */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'Associazione'      => 'required|string|max:255',
            'email'             => 'required|email|unique:associazioni,email',
            'provincia'         => 'required|string|exists:ter_cities,sigla_provincia',
            'citta'             => 'required|string|max:100',
            'cap'               => 'nullable|string|max:10',
            'indirizzo'         => 'nullable|string|max:255',
            'note'              => 'nullable|string',
            // utente iniziale
            'adminuser_name'    => 'required|string|max:255',
            'adminuser_email'   => 'required|email|unique:users,email',
        ]);

        $now    = now();
        $userId = auth()->id();

        // 1) crea l’associazione (incluso CAP)
        $associazioneId = DB::table('associazioni')->insertGetId([
            'Associazione' => $validated['Associazione'],
            'email'        => $validated['email'],
            'provincia'    => $validated['provincia'],
            'citta'        => $validated['citta'],
            'cap'          => $validated['cap'] ?? null,
            'indirizzo'    => $validated['indirizzo']?? null,
            'note'         => $validated['note'] ?? null,
            'active'       => true,
            'created_by'   => $userId,
            'updated_by'   => $userId,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        // 2) ruolo utente iniziale
        $isFirst  = DB::table('associazioni')->whereNull('deleted_at')->count() === 1;
        $roleName = $isFirst ? 'AdminUser' : 'User';
        $role     = Role::where('name', $roleName)->firstOrFail();

        // 3) crea utente e collega all’associazione
        $password = Str::random(10);
        $user = User::create([
            'firstname'      => '',
            'lastname'       => '',
            'username'       => $validated['adminuser_name'],
            'email'          => $validated['adminuser_email'],
            'password'       => Hash::make($password),
            'role_id'        => $role->id,
            'active'         => true,
            'IdAssociazione' => $associazioneId,
        ]);
        $user->assignRole($role);

        // 4) invio email reset password
        $token    = Password::createToken($user);
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));
        Mail::to($user)->send(new SupervisorInvite($user, $resetUrl));

        return redirect()
            ->route('dashboard')
            ->with('success', "Associazione e utente creati. Controlla la mail di {$user->email}.");
    }

    /** =========================
     *  POST /associazioni/{id}/toggle-active
     *  ========================= */
    public function toggleActive($id)
    {
        Associazione::toggleActive((int) $id);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Stato attivazione dell’associazione modificato!');
    }

    /** =========================
     *  DELETE /associazioni/{id}
     *  ========================= */
    public function destroy($id)
    {
        Associazione::softDelete((int) $id);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Associazione eliminata!');
    }

    /** =========================
     *  GET /associazioni/create
     *  ========================= */
    public function create()
    {
        $cities = Cities::getAll();

        // CAP per combo (stesso approccio di Aziende Sanitarie)
        // tabella vista negli screenshot: ter_cities_cap
        $caps = DB::table('ter_cities_cap')
            ->select('cap', 'denominazione_ita', 'sigla_provincia')
            ->orderBy('sigla_provincia')
            ->orderBy('denominazione_ita')
            ->orderBy('cap')
            ->get();

        return view('associazioni.create', compact('cities', 'caps'));
    }

    /** =========================
     *  GET /associazioni/{id}/edit
     *  ========================= */
    public function edit($id)
    {
        $associazione = Associazione::findById((int) $id);
        if (! $associazione) {
            abort(404, 'Associazione non trovata');
        }

        $adminUser = Associazione::getAdminUserFor((int) $id);
        $cities    = Cities::getAll();

        // CAP per combo
        $caps = DB::table('ter_cities_cap')
            ->select('cap', 'denominazione_ita', 'sigla_provincia')
            ->orderBy('sigla_provincia')
            ->orderBy('denominazione_ita')
            ->orderBy('cap')
            ->get();

        return view('associazioni.edit', compact('associazione', 'adminUser', 'cities', 'caps'));
    }

    /** =========================
     *  PATCH /associazioni/{id} (update)
     *  ========================= */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'Associazione' => 'required|string|max:255',
            'email'        => 'required|email',
            'provincia'    => 'required|string|exists:ter_cities,sigla_provincia',
            'citta'        => 'required|string|max:100',
            'cap'          => 'nullable|string|max:10',
            'indirizzo'    => 'nullable|string|max:255',
            'note'         => 'nullable|string',
        ]);

        DB::table('associazioni')
            ->where('IdAssociazione', (int) $id)
            ->update([
                'Associazione' => $data['Associazione'],
                'email'        => $data['email'],
                'provincia'    => $data['provincia'],
                'citta'        => $data['citta'],
                'cap'          => $data['cap'] ?? null,
                'indirizzo'    => $data['indirizzo'] ?? null,
                'note'         => $data['note'] ?? null,
                'updated_by'   => auth()->id(),
                'updated_at'   => now(),
            ]);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Associazione aggiornata con successo.');
    }
  
}

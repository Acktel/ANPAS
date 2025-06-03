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

class AssociazioniController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** GET /associazioni */
    public function index(){
       
        // Qui Auth::user() NON è null, perché siamo già in un metodo protetto da auth()
        Log::info("Entrato in AssociazioniController@index"); 

        $associazioni = collect(Associazione::getAll(request()));
        return view('associazioni.index', compact('associazioni'));
    }

    /** GET /associazioni/data */
    public function getData(Request $request)
    {
        $data = Associazione::getAll($request);
        return response()->json($data);
    }

    /** POST /associazioni */
    public function store(Request $request)
    {
        $data = $request->validate([
            'Associazione'      => 'required|string|max:255',
            'email'             => 'required|email|unique:associazioni,email',
            'provincia'         => 'required|string|max:100',
            'citta'             => 'required|string|max:100',
            'adminuser_name'   => 'required|string|max:255',
            'adminuser_email'  => 'required|email|unique:users,email',
        ]);

        // 1) crea l’associazione
        $associazioneId = Associazione::createAssociation($data);

        // 2) recupera l'ID del ruolo "AdminUser"
        $adminuserRole = Role::where('name', 'AdminUser')->first();

        // 3) crea l’utente adminuser (assegnando role_id anziché type)
        $password = Str::random(10);
        $user = User::create([
            'firstname'       => $data['adminuser_name'],
            'lastname'        => '',
            'username'        => $data['adminuser_email'],
            'email'           => $data['adminuser_email'],
            'password'        => Hash::make($password),
            'role_id'         => $adminuserRole->id,
            'active'          => true,
            'IdAssociazione'  => $associazioneId,
        ]);

        // 4) assegna il ruolo tramite Spatie (popola la pivot model_has_roles)
        $user->assignRole($adminuserRole);

        // 5) invia email di reset password
        $token = Password::createToken($user);
        $resetUrl = url(route('password.reset', [
            'token' => $token, 
            'email' => $user->email,
        ], false));
        Mail::to($user)->send(new AdminUserInvite($user, $resetUrl));

        return redirect()
            ->route('dashboard')
            ->with('success', "Associazione e AdminUser creati. Controlla la mail di {$user->email}.");
    }

    /** POST /associazioni/{id}/toggle-active */
    public function toggleActive($id)
    {
        Associazione::toggleActive($id);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Stato attivazione dell\'associazione modificato!');
    }

    /** DELETE /associazioni/{id} */
    public function destroy($id)
    {
        Associazione::softDelete($id);

        return redirect()
            ->route('associazioni.index')
            ->with('success', 'Associazione eliminata!');
    }

    public function create()
    {
        return view('associazioni.create');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller {
    use AuthenticatesUsers;

    /** Dove reindirizzare dopo il login */
    protected $redirectTo = '/home';

    public function __construct() {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /** Campo usato per l’autenticazione: email */
    protected function username(): string {
        return 'email';
    }

    /** Validazione lato server prima del tentativo di login */
    protected function validateLogin(Request $request): void {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        // niente controllo "email", solo presenza
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);
    }

    /**
     * Credenziali usate per attempt().
     * Includo 'active' => 1 se usi la colonna 'active' su users.
     */
    protected function credentials(Request $request): array {
        $base = [
            'email'    => mb_strtolower(trim((string) $request->input('email'))),
            'password' => $request->input('password'),
        ];

        // se NON usi la colonna 'active', elimina questa riga
        $base['active'] = 1;

        return $base;
    }

    /**
     * (Opzionale) Messaggio custom: puoi distinguere utente disattivato da credenziali errate.
     * Lasciato all’implementazione del trait per semplicità.
     */
    // protected function sendFailedLoginResponse(Request $request)
    // {
    //     return parent::sendFailedLoginResponse($request);
    // }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RiepilogoCosti;

class HomeController extends Controller {
    public function __construct() {
        $this->middleware('auth');
    }

    public function index(Request $request) {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();
        $isImpersonating = session()->has('impersonate');

        $associazioni = \App\Models\Dipendente::getAssociazioni($user, $isImpersonating);
        $selectedAssoc = $request->query('idAssociazione') ?? session('idAssociazione');

        // Se è un Admin e ha selezionato un'associazione, salvala in sessione
        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            if ($selectedAssoc) {
                session(['idAssociazione' => $selectedAssoc]);
            }
        } else {
            $selectedAssoc = $user->IdAssociazione;
            session(['idAssociazione' => $selectedAssoc]);
        }

        $dati = \App\Models\RiepilogoCosti::getTotaliPerTipologia($anno, $selectedAssoc);

        return view('dashboard', compact('dati', 'anno', 'associazioni', 'selectedAssoc'));
    }
}

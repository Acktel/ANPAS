<?php

namespace App\Http\Controllers;

use App\Models\Dipendente;
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

    $associazioni = Dipendente::getAssociazioni($user, $isImpersonating);

    // prendi dal GET se presente, altrimenti session
    $selectedAssoc = $request->query('idAssociazione', session('idAssociazione', null));

    // Se è un Admin e ha selezionato un'associazione, salvala in sessione
    if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
        // salviamo solo se il parametro è presente nella request (anche se è "0")
        if ($request->has('idAssociazione')) {
            session(['idAssociazione' => $selectedAssoc]);
        }
    } else {
        $selectedAssoc = $user->IdAssociazione;
        session(['idAssociazione' => $selectedAssoc]);
    }

    $dati = RiepilogoCosti::getTotaliPerTipologia($anno, $selectedAssoc);

    return view('dashboard', compact('dati', 'anno', 'associazioni', 'selectedAssoc'));
}

}

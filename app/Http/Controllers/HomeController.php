<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RiepilogoCosti;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $anno = session('anno_riferimento', now()->year);
        $user = Auth::user();

        $idAssociazione = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']) 
            ? null 
            : $user->IdAssociazione;

        $dati = RiepilogoCosti::getTotaliPerTipologia($anno, $idAssociazione);

        return view('dashboard', compact('dati', 'anno'));
    }
}

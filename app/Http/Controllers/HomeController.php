<?php
// app/Http/Controllers/HomeController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct()
    {
        // Protegge tutte le azioni da auth
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin','supervisor'])) {
            // Tutte le associazioni
            $records = DB::table('associazioni')
                         ->orderBy('Associazione')
                         ->get();
            $columns = ['idAssociazione','Associazione'];
            $title   = 'Elenco Associazioni';
        } else {
            // Solo la sua associazione (si assume che in "utenti" esista idAssociazione)
            $myAss = DB::table('utenti')
                       ->where('idUtente', $user->id) 
                       ->value('idAssociazione');

            $records = DB::table('associazioni')
                         ->where('idAssociazione', $myAss)
                         ->get();
            $columns = ['idAssociazione','Associazione'];
            $title   = 'La Tua Associazione';
        }

        return view('home', compact('records','columns','title'));
    }
}

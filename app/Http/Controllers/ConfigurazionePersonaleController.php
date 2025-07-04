<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Qualifica;
use App\Models\ContrattoApplicato;

class ConfigurazionePersonaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $qualifiche = Qualifica::getAll();
        $contratti = ContrattoApplicato::getAll();

        return view('configurazioni.personale', compact('qualifiche', 'contratti'));
    }

    public function storeQualifica(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255|unique:qualifiche,nome',
            'livello_mansione' => 'required|string|max:255',
        ]);

        Qualifica::createQualifica($data);

        return back()->with('success', 'Qualifica aggiunta.');
    }

    public function destroyQualifica(int $id)
    {
        $used = DB::table('dipendenti')->whereRaw("FIND_IN_SET(?, Qualifica)", [$id])->exists();
        if ($used) {
            return back()->withErrors(['error' => 'Qualifica in uso da uno o più dipendenti.']);
        }

        if (!Qualifica::deleteById($id)) {
            return back()->withErrors(['error' => 'Qualifica non trovata.']);
        }

        return back()->with('success', 'Qualifica rimossa.');
    }

    public function storeContratto(Request $request)
    {
        $data = $request->validate([
            'nome' => 'required|string|max:255|unique:contratti_applicati,nome',
        ]);

        ContrattoApplicato::createContratto($data);

        return back()->with('success', 'Contratto applicato aggiunto.');
    }

    public function destroyContratto(int $id)
    {
        $used = DB::table('dipendenti')->where('ContrattoApplicato', $id)->exists();
        if ($used) {
            return back()->withErrors(['error' => 'Contratto in uso da uno o più dipendenti.']);
        }

        if (!ContrattoApplicato::deleteById($id)) {
            return back()->withErrors(['error' => 'Contratto non trovato.']);
        }

        return back()->with('success', 'Contratto rimosso.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RiepilogoVoceConfig;

class ConfigurazioneRiepilogoController extends Controller
{
    public function __construct() { $this->middleware('auth'); }

    public function index()
    {
        $this->authorizeRoles();
 
        $tipologie       = RiepilogoVoceConfig::listTipologie(); 
        $vociByTipologia = RiepilogoVoceConfig::allByTipologia();    
        
        return view('configurazioni.riepilogo', compact('tipologie','vociByTipologia'));
    }

    public function store(Request $request)
    {
        $this->authorizeRoles();

        $data = $request->validate([
            'idTipologiaRiepilogo' => 'required|integer|exists:tipologia_riepilogo,id',
            'descrizione'          => 'required|string|max:500',
            'ordinamento'          => 'nullable|integer|min:0',
            // niente boolean qui: normalizziamo noi
        ]);

        // pattern hidden(0)+checkbox(1) dal Blade -> qui diventa 0/1 sicuro
        $data['attivo'] = (int) $request->input('attivo', 0);

        RiepilogoVoceConfig::createVoce($data);

        return back()->with('success', 'Voce aggiunta.');
    }

    public function update(Request $request, int $id)
    {
        $this->authorizeRoles();

        $data = $request->validate([
            'idTipologiaRiepilogo' => 'required|integer|exists:tipologia_riepilogo,id',
            'descrizione'          => 'required|string|max:500',
            'ordinamento'          => 'nullable|integer|min:0',
            // niente boolean qui
        ]);

        // idem: 0/1
        $data['attivo'] = (int) $request->input('attivo', 0);

        RiepilogoVoceConfig::updateVoce($id, $data);

        return back()->with('success', 'Voce aggiornata.');
    }

    public function destroy(int $id)
    {
        $this->authorizeRoles();
        RiepilogoVoceConfig::deleteVoce($id);
        return back()->with('success', 'Voce rimossa.');
    }

    public function reorder(Request $request)
    {
        $this->authorizeRoles();

        $order = $request->input('order');
        if (is_string($order)) {
            $order = json_decode($order, true) ?? [];
        }
        if (!is_array($order) || empty($order)) {
            return response()->json(['ok' => false, 'message' => 'Formato riordino non valido'], 422);
        }

        RiepilogoVoceConfig::reorder($order);

        return response()->json(['ok' => true]);
    }

    private function authorizeRoles(): void
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['SuperAdmin','Admin'])) {
            abort(403, 'Accesso negato');
        }
    }
}

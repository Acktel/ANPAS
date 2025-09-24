<?php
// app/Http/Controllers/ConfigurazionePersonaleController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Qualifica;
use App\Models\ContrattoApplicato;
use App\Models\LivelloMansione;

class ConfigurazionePersonaleController extends Controller {
    public function __construct() { $this->middleware('auth'); }

    public function index() {
        $qualifiche = Qualifica::getAll();
        $contratti  = ContrattoApplicato::getAll();
        $livelli    = LivelloMansione::getAll();

        return view('configurazioni.personale', compact('qualifiche','contratti','livelli'));
    }

    /** ✅ Aggiorna ordinamento/attivo singola qualifica (nome non modificabile) */
    public function updateQualifica(Request $request, int $id) {
        $data = $request->validate([
            'ordinamento' => 'nullable|integer|min:0',
            // attivo arriva come hidden(0)+checkbox(1)
        ]);
        $data['attivo'] = (int)$request->input('attivo', 0);

        Qualifica::updateById($id, $data);
        return back()->with('success', 'Qualifica aggiornata.');
    }

    /** ✅ Riordino drag&drop */
    public function reorderQualifiche(Request $request) {
        $order = $request->input('order');
        if (is_string($order)) $order = json_decode($order, true) ?? [];
        if (!is_array($order) || empty($order)) {
            return response()->json(['ok'=>false,'message'=>'Formato riordino non valido'], 422);
        }
        Qualifica::reorder($order);
        return response()->json(['ok'=>true]);
    }

    /** ❌ Disabilitiamo creazione/eliminazione per coerenza con ID fissi */
    public function storeQualifica()  { abort(403, 'Creazione non consentita.'); }
    public function destroyQualifica(){ abort(403, 'Eliminazione non consentita.'); }

    // ---- contratti/livelli rimangono come sono ----
    public function storeContratto(Request $request) {
        $data = $request->validate([
            'nome' => 'string|max:255|unique:contratti_applicati,nome',
        ]);
        ContrattoApplicato::createContratto($data);
        return back()->with('success', 'Contratto applicato aggiunto.');
    }

    public function destroyContratto(int $id) {
        $used = DB::table('dipendenti')->where('ContrattoApplicato', $id)->exists();
        if ($used) return back()->withErrors(['error' => 'Contratto in uso da uno o più dipendenti.']);
        if (!ContrattoApplicato::deleteById($id)) return back()->withErrors(['error' => 'Contratto non trovato.']);
        return back()->with('success', 'Contratto rimosso.');
    }

    public function storeLivelloMansione(Request $request) {
        $data = $request->validate([
            'nome' => 'required|string|max:255|unique:livello_mansione,nome',
        ]);
        LivelloMansione::createLivello($data);
        return back()->with('success', 'Livello mansione aggiunto.');
    }

    public function destroyLivelloMansione(int $id) {
        $used = DB::table('dipendenti_livelli_mansione')->where('idLivelloMansione', $id)->exists();
        if ($used) return back()->withErrors(['error' => 'Livello in uso da uno o più dipendenti.']);
        if (!LivelloMansione::deleteById($id)) return back()->withErrors(['error' => 'Livello non trovato.']);
        return back()->with('success', 'Livello mansione rimosso.');
    }

    public static function getConfigurazionePersonale() {
        return [
            'qualifiche' => Qualifica::getAll(),
            'contratti'  => ContrattoApplicato::getAll(),
            'livelli'    => LivelloMansione::getAll(),
        ];
    }
}

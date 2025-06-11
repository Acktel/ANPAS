<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Automezzo;
use App\Models\AutomezzoKm;

class AutomezziController extends Controller
{
    /**
 * Elenco automezzi (filtrato per associazione o tutti per Admin/SuperAdmin/Supervisor).
 */
public function index()
{
    $user = Auth::user();
    $anno = session('anno_riferimento', now()->year);

    if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
        // Usa metodo custom con filtro anno
        $automezzi = Automezzo::getAll($anno);
    } else {
        $idAssoc = $user->IdAssociazione;
        if (! $idAssoc) {
            abort(403, "Associazione non trovata per l'utente.");
        }

        // Usa metodo custom con filtro per anno e associazione
        $automezzi = Automezzo::getByAssociazione($idAssoc, $anno);
    }

    return view('automezzi.index', compact('automezzi', 'anno'));
}

    /**
     * Mostra il form di creazione.
     */
    public function create()
    {
        // Lista associazioni per Admin
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        // Lista anni
        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        // Se l’utente è “di associazione”, forziamo il select su una sola associazione
        if (! in_array(Auth::user()->role_id, [1, 2, 3])) {
            $associazioni = $associazioni
                ->where('idAssociazione', Auth::user()->idAssociazione);
        }

        return view('automezzi.create', compact('associazioni', 'anni'));
    }

    /**
     * Riceve POST del form e crea un nuovo automezzo.
     */
    public function store(Request $request)
    {
        $rules = [
            'idAssociazione'                => 'required|exists:associazioni,idAssociazione',
            'idAnno'                        => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'Automezzo'                     => 'required|string|max:255',
            'Targa'                         => 'required|string|max:50',
            'CodiceIdentificativo'          => 'required|string|max:100',
            'AnnoPrimaImmatricolazione'     => 'required|integer|min:1900|max:' . date('Y'),
            'Modello'                       => 'required|string|max:255',
            'TipoVeicolo'                   => 'required|string|max:100',
            'KmRiferimento'                 => 'required|numeric|min:0',
            'KmTotali'                      => 'required|numeric|min:0',
            'TipoCarburante'                => 'required|string|max:50',
            'DataUltimaAutorizzazioneSanitaria' => 'nullable|date',
            'DataUltimoCollaudo'            => 'nullable|date',
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            Automezzo::createAutomezzo($validated);
            DB::commit();

            return redirect()
                ->route('automezzi.index')
                ->with('success', 'Automezzo creato correttamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Errore interno: impossibile salvare l’automezzo.']);
        }
    }

    /**
     * Dettaglio di un singolo automezzo.
     */
    public function show(int $idAutomezzo)
    {
        $automezzo = DB::table('automezzi as a')
            ->join('associazioni as s', 'a.idAssociazione', '=', 's.idAssociazione')
            ->select([
                'a.idAutomezzo',
                's.idAssociazione',
                'a.idAnno', 
                'a.Automezzo',
                'a.Targa',
                'a.CodiceIdentificativo',
                'a.AnnoPrimaImmatricolazione',
                'a.Modello',
                'a.TipoVeicolo',
                'a.KmRiferimento',
                'a.KmTotali',
                'a.TipoCarburante',
                'a.DataUltimaAutorizzazioneSanitaria',
                'a.DataUltimoCollaudo',
                'a.created_at'
            ])
            ->where('a.idAutomezzo', $idAutomezzo)
            ->first();

        if (! $automezzo) {
            abort(404);
        }

        return view('automezzi.show', ['automezzo' => $automezzo]);
    }

    /**
     * Mostra il form di modifica per un automezzo esistente.
     */
    public function edit(int $idAutomezzo)
    {
        $automezzo = Automezzo::getById($idAutomezzo);
        if (! $automezzo) {
            abort(404);
        }

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->orderBy('Associazione')
            ->get();

        $anni = DB::table('anni')
            ->select('idAnno', 'anno')
            ->orderBy('anno', 'desc')
            ->get();

        return view('automezzi.edit', compact('automezzo', 'associazioni', 'anni'));
    }

    /**
     * Riceve PUT per aggiornare un automezzo.
     */
    public function update(Request $request, int $idAutomezzo)
    {
        $rules = [
            'idAssociazione'                => 'required|exists:associazioni,idAssociazione',
            'idAnno'                        => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'Automezzo'                     => 'required|string|max:255',
            'Targa'                         => 'required|string|max:50',
            'CodiceIdentificativo'          => 'required|string|max:100',
            'AnnoPrimaImmatricolazione'     => 'required|integer|min:1900|max:' . date('Y'),
            'Modello'                       => 'required|string|max:255',
            'TipoVeicolo'                   => 'required|string|max:100',
            'KmRiferimento'                 => 'required|numeric|min:0',
            'KmTotali'                      => 'required|numeric|min:0',
            'TipoCarburante'                => 'required|string|max:50',
            'DataUltimaAutorizzazioneSanitaria' => 'nullable|date',
            'DataUltimoCollaudo'            => 'nullable|date',
        ];

        $validated = $request->validate($rules);

        DB::beginTransaction();
        try {
            Automezzo::updateAutomezzo($idAutomezzo, $validated);
            DB::commit();

            return redirect()
                ->route('automezzi.index')
                ->with('success', 'Automezzo aggiornato correttamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Errore interno: impossibile aggiornare.']);
        }
    }

    /**
     * Elimina un automezzo e tutti i suoi Km.
     */
    public function destroy(int $idAutomezzo)
    {
        $automezzo = Automezzo::getById($idAutomezzo);
        if (! $automezzo) {
            abort(404);
        }

        DB::beginTransaction();
        try {
            Automezzo::deleteAutomezzo($idAutomezzo);
            DB::commit();

            return redirect()
                ->route('automezzi.index')
                ->with('success', 'Automezzo eliminato correttamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withErrors(['error' => 'Errore interno: impossibile eliminare.']);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\AziendaSanitaria;
use App\Models\LottoAziendaSanitaria;

class AziendeSanitarieController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $anno = session('anno_riferimento', now()->year);

        // lista convenzioni (usata per la select)
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        // valore selezionato (request -> session -> null)
        if ($request->has('idConvenzione')) {
            session(['convenzione_selezionata' => $request->idConvenzione]);
        }
        $selectedConvenzione = session('convenzione_selezionata') ?? null;

        // carico le aziende sanitarie (il filtro verrà applicato da getData / model)
        $aziende = AziendaSanitaria::getAllWithConvenzioni(); // carico tutto per la view iniziale

        return view('aziende_sanitarie.index', compact(
            'anno',
            'convenzioni',
            'selectedConvenzione',
            'aziende'
        ));
    }

    public function getData(Request $request): JsonResponse
    {
        // legge filtro da request (inviato dalla DataTable via ajax)
        $idConvenzione = $request->input('idConvenzione') ?? session('convenzione_selezionata');

        $data = AziendaSanitaria::getAllWithConvenzioni($idConvenzione);

        return response()->json(['data' => $data]);
    }

    public function create()
    {
        $user = Auth::user();
        $anni = DB::table('anni')->orderBy('anno', 'desc')->get();

        $associazioni = DB::table('associazioni')
            ->select('idAssociazione', 'Associazione')
            ->whereNull('deleted_at')
            ->orderBy('Associazione')
            ->get();

        $aziendeSanitarie = DB::table('aziende_sanitarie')
            ->select('idAziendaSanitaria', 'Nome')
            ->orderBy('Nome')
            ->get();

        // carico le convenzioni
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        // per uniformità con edit, passo lotti vuoti
        $lotti = collect();

        return view('aziende_sanitarie.create', compact(
            'anni',
            'associazioni',
            'convenzioni',
            'aziendeSanitarie',
            'lotti'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Nome'           => 'required|string|max:150',
            'Indirizzo'      => 'nullable|string|max:255',
            'mail'           => 'nullable|email|max:150',
            'note'           => 'nullable|string',
            'convenzioni'    => 'nullable|array',
            'convenzioni.*'  => 'exists:convenzioni,idConvenzione',

            // Lotti (nome + descrizione + _delete)
            'lotti'               => 'nullable|array',
            'lotti.*.id'          => 'nullable|integer',
            'lotti.*.nomeLotto'   => 'nullable|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',
            'lotti.*._delete'     => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated) {
            $id = AziendaSanitaria::createSanitaria($validated);

            if (!empty($validated['convenzioni'])) {
                AziendaSanitaria::syncConvenzioni($id, $validated['convenzioni']);
            }

            // Anticipo l’errore di duplicato (oltre all'unique DB)
            $names = [];
            foreach (($validated['lotti'] ?? []) as $row) {
                if (!empty($row['_delete'])) continue;
                $name = trim((string)($row['nomeLotto'] ?? ''));
                if ($name === '') continue;
                $k = mb_strtolower($name);
                if (isset($names[$k])) {
                    abort(422, "Nome lotto duplicato: '{$name}'");
                }
                $names[$k] = true;
            }

            // Sync lotti (insert/update/delete)
            LottoAziendaSanitaria::syncForAzienda($id, $validated['lotti'] ?? []);

            return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda creata.');
        });
    }

    public function edit(int $id)
    {
        // Singola azienda sanitaria
        $azienda = DB::table('aziende_sanitarie')
            ->where('idAziendaSanitaria', $id)
            ->first();
        abort_if(!$azienda, 404);

        // Tutte le convenzioni disponibili
        $convenzioni = DB::table('convenzioni')
            ->select('idConvenzione', 'Convenzione')
            ->orderBy('Convenzione')
            ->get();

        // Convenzioni collegate all’azienda
        $convenzioniSelezionate = DB::table('azienda_sanitaria_convenzione')
            ->where('idAziendaSanitaria', $id)
            ->pluck('idConvenzione')
            ->toArray();

        // Lotti dell’azienda
        $lotti = LottoAziendaSanitaria::getByAzienda($id);

        return view('aziende_sanitarie.edit', compact(
            'azienda',
            'convenzioni',
            'convenzioniSelezionate',
            'lotti'
        ));
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'Nome'           => 'required|string|max:150',
            'Indirizzo'      => 'nullable|string|max:255',
            'mail'           => 'nullable|email|max:150',
            'note'           => 'nullable|string',
            'convenzioni'    => 'nullable|array',
            'convenzioni.*'  => 'exists:convenzioni,idConvenzione',

            'lotti'               => 'nullable|array',
            'lotti.*.id'          => 'nullable|integer',
            'lotti.*.nomeLotto'   => 'nullable|string|max:255',
            'lotti.*.descrizione' => 'nullable|string',
            'lotti.*._delete'     => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            AziendaSanitaria::updateSanitaria($id, $validated);

            if (array_key_exists('convenzioni', $validated)) {
                AziendaSanitaria::syncConvenzioni($id, $validated['convenzioni']);
            }

            // check duplicati lato app
            $names = [];
            foreach (($validated['lotti'] ?? []) as $row) {
                if (!empty($row['_delete'])) continue;
                $name = trim((string)($row['nomeLotto'] ?? ''));
                if ($name === '') continue;
                $k = mb_strtolower($name);
                if (isset($names[$k])) {
                    abort(422, "Nome lotto duplicato: '{$name}'");
                }
                $names[$k] = true;
            }

            LottoAziendaSanitaria::syncForAzienda($id, $validated['lotti'] ?? []);

            return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda aggiornata.');
        });
    }

    public function destroy(int $id)
    {
        AziendaSanitaria::deleteSanitaria($id);
        return redirect()->route('aziende-sanitarie.index')->with('success', 'Azienda eliminata.');
    }
}

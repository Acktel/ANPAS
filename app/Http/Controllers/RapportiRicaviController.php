<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Convenzione;
use App\Models\RapportoRicavo;
use App\Models\Associazione;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RapportiRicaviController extends Controller
{
    public function index()
    {
        return view('rapporti_ricavi.index');
    }

    /** per DataTables */
    public function getData()
    {
        $user = Auth::user();
        if (session()->has('impersonate')) {
            $user = User::find(session('impersonate'));
        }

        $anno = session('anno_riferimento', now()->year);
        $convenzioni = Convenzione::getByAnno($anno, $user)->sortBy('idConvenzione')->values();
        $ricavi = RapportoRicavo::getGroupedByConvenzione($anno, $user);

        $totale = $ricavi->sum('Rimborso');
        $labels = [];
        foreach ($convenzioni as $c) {
            $labels['c'.$c->idConvenzione] = $c->Convenzione;
        }

        $riga = [
            'is_totale'       => -1,
            'Associazione'    => $user->hasAnyRole(['SuperAdmin','Admin'])
                                  ? 'Tutte'
                                  : $user->associazione->Associazione,
            'TotaleEsercizio' => number_format($totale,2,',','.'),
        ];

        $sumPercent = 0;
        $last = count($convenzioni) - 1;
        foreach ($convenzioni as $i => $conv) {
            $key = 'c'.$conv->idConvenzione;
            $rimborso = optional($ricavi->firstWhere('idConvenzione',$conv->idConvenzione))->Rimborso ?? 0;
            $riga[$key.'_rimborso'] = number_format($rimborso,2,',','.');
            if ($i < $last) {
                $p = $totale>0 ? round($rimborso/$totale*100,2) : 0;
                $riga[$key.'_percent'] = $p;
                $sumPercent += $p;
            } else {
                $riga[$key.'_percent'] = max(0,round(100-$sumPercent,2));
            }
        }

        return response()->json([
            'data'   => [$riga],
            'labels' => $labels,
        ]);
    }

    public function create()
    {
        $user = Auth::user();
        // se super/admin prendo tutte le associazioni
        if ($user->hasAnyRole(['SuperAdmin','Admin'])) {
            $associazioni = \DB::table('associazioni')
                ->select('idAssociazione','Associazione')
                ->whereNull('deleted_at')
                ->orderBy('Associazione')
                ->get();
        } else {
            $associazioni = collect([
                (object)[
                    'idAssociazione' => $user->idAssociazione,
                    'Associazione'   => $user->associazione->Associazione,
                ]
            ]);
        }
        $anno = session('anno_riferimento', now()->year);
        $convenzioni = Convenzione::getByAnno($anno,$user)->sortBy('idConvenzione')->values();

        return view('rapporti_ricavi.create', compact('associazioni','convenzioni','anno'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $idAss = (int)$request->input('idAssociazione', $user->idAssociazione);
        $anno  = session('anno_riferimento');

        foreach ($request->input('ricavi', []) as $idConv => $rimborso) {
            if (!is_numeric($idConv) || is_null($rimborso)) continue;
            RapportoRicavo::upsert(
                (int)$idConv,
                $idAss,
                $anno,
                (float)$rimborso
            );
        }
        return redirect()->route('rapporti-ricavi.index')
                         ->with('success','Ricavi salvati correttamente.');
    }

    public function edit(int $idAssociazione)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['SuperAdmin','Admin']) && $user->idAssociazione !== $idAssociazione) {
            abort(403);
        }
        $anno = session('anno_riferimento');
        $convenzioni   = Convenzione::getByAnno($anno,$user)->sortBy('idConvenzione')->values();
        $valori        = RapportoRicavo::getByAssociazione($anno,$idAssociazione);
        $associazione  = \DB::table('associazioni')
                            ->where('idAssociazione',$idAssociazione)
                            ->value('Associazione');

        return view('rapporti_ricavi.edit', compact(
            'convenzioni','valori','idAssociazione','associazione','anno'
        ));
    }

    public function update(Request $request, int $idAssociazione)
    {
        $anno = session('anno_riferimento');
        RapportoRicavo::deleteByAssociazione($idAssociazione,$anno);

        foreach ($request->input('ricavi', []) as $idConv => $rimborso) {
            if (!is_numeric($idConv)) continue;
            RapportoRicavo::upsert(
                (int)$idConv,
                $idAssociazione,
                $anno,
                (float)$rimborso
            );
        }
        return redirect()->route('rapporti-ricavi.index')
                         ->with('success','Ricavi aggiornati.');
    }

    public function show(int $idAssociazione)
    {
        // qui potresti semplicemente redirigere a edit, oppure:
        return $this->edit($idAssociazione);
    }

    public function destroy(int $idAssociazione)
    {
        $anno = session('anno_riferimento');
        RapportoRicavo::deleteByAssociazione($idAssociazione,$anno);
        return redirect()->route('rapporti-ricavi.index')
                         ->with('success','Dati eliminati.');
    }
}

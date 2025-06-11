<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Convenzione;

class ConvenzioniController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        if ($user->hasAnyRole(['SuperAdmin','Admin','Supervisor'])) {
            $convenzioni = Convenzione::getAll();
        } else {
            $convenzioni = Convenzione::getByAssociazione($user->IdAssociazione);
        }
        return view('convenzioni.index', compact('convenzioni'));
    }

    public function create()
    {
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione','Associazione')
            ->orderBy('Associazione')->get();
        $anni = DB::table('anni')
            ->select('idAnno','anno')
            ->orderBy('anno','desc')->get();
        return view('convenzioni.create', compact('associazioni','anni'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idAssociazione'          => 'required|exists:associazioni,idAssociazione',
            'idAnno'                  => 'required|exists:anni,idAnno',
            'Convenzione'             => 'required|string|max:100',
            'lettera_identificativa'  => 'required|string|max:5',
        ]);
        Convenzione::createConvenzione($data);
        return redirect()->route('convenzioni.index')
                         ->with('success','Convenzione creata.');
    }

    public function edit(int $id)
    {
        $conv = Convenzione::getById($id);
        if (! $conv) abort(404);
        $associazioni = DB::table('associazioni')
            ->select('idAssociazione','Associazione')
            ->orderBy('Associazione')->get();
        $anni = DB::table('anni')
            ->select('idAnno','anno')
            ->orderBy('anno','desc')->get();
        return view('convenzioni.edit', compact('conv','associazioni','anni'));
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'idAssociazione'          => 'required|exists:associazioni,idAssociazione',
            'idAnno'                  => 'required|exists:anni,idAnno',
            'Convenzione'             => 'required|string|max:100',
            'lettera_identificativa'  => 'required|string|max:5',
        ]);
        Convenzione::updateConvenzione($id, $data);
        return redirect()->route('convenzioni.index')
                         ->with('success','Convenzione aggiornata.');
    }

    public function destroy(int $id)
    {
        if (! Convenzione::getById($id)) abort(404);
        Convenzione::deleteConvenzione($id);
        return redirect()->route('convenzioni.index')
                         ->with('success','Convenzione eliminata.');
    }
}

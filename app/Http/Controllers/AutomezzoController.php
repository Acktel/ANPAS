<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutomezzoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** GET /automezzi */
    public function index()
    {
        $automezzi = DB::table('automezzi as a')
            ->join('associazioni as asso', 'a.idAssociazione', '=', 'asso.idAssociazione')
            ->join('anni as an',          'a.idAnno',         '=', 'an.idAnno')
            ->select([
                'a.idAutomezzo',
                'asso.Associazione',
                'an.Anno',
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
            ])
            ->orderBy('a.Automezzo')
            ->get();

        return view('automezzi.index', compact('automezzi'));
    }

    /** GET /automezzi/create */
    public function create()
    {
        $associazioni = DB::table('associazioni')->orderBy('Associazione')->get();
        $anni         = DB::table('anni')->orderBy('Anno')->get();

        return view('automezzi.create', compact('associazioni', 'anni'));
    }

    /** POST /automezzi */
    public function store(Request $request)
    {
        $request->validate([
            'idAssociazione'                         => 'required|exists:associazioni,idAssociazione',
            'idAnno'                                 => 'required|exists:anni,idAnno',
            'Automezzo'                              => 'required|string|max:100',
            'Targa'                                  => 'nullable|string|max:20',
            'CodiceIdentificativo'                   => 'nullable|string|max:50',
            'AnnoPrimaImmatricolazione'              => 'nullable|digits:4',
            'Modello'                                => 'nullable|string|max:100',
            'TipoVeicolo'                            => 'nullable|string|max:50',
            'KmRiferimento'                          => 'nullable|integer',
            'KmTotali'                               => 'nullable|integer',
            'TipoCarburante'                         => 'nullable|string|max:30',
            'DataUltimaAutorizzazioneSanitaria'      => 'nullable|date',
            'DataUltimoCollaudo'                     => 'nullable|date',
        ]);

        DB::table('automezzi')->insert([
            'idAssociazione'                         => $request->idAssociazione,
            'idAnno'                                 => $request->idAnno,
            'Automezzo'                              => $request->Automezzo,
            'Targa'                                  => $request->Targa,
            'CodiceIdentificativo'                   => $request->CodiceIdentificativo,
            'AnnoPrimaImmatricolazione'              => $request->AnnoPrimaImmatricolazione,
            'Modello'                                => $request->Modello,
            'TipoVeicolo'                            => $request->TipoVeicolo,
            'KmRiferimento'                          => $request->KmRiferimento,
            'KmTotali'                               => $request->KmTotali,
            'TipoCarburante'                         => $request->TipoCarburante,
            'DataUltimaAutorizzazioneSanitaria'      => $request->DataUltimaAutorizzazioneSanitaria,
            'DataUltimoCollaudo'                     => $request->DataUltimoCollaudo,
            'created_at'                             => now(),
            'updated_at'                             => now(),
        ]);

        return redirect()
            ->route('automezzi.index')
            ->with('status', 'Automezzo creato correttamente.');
    }

    /** GET /automezzi/{automezzo} */
    public function show($id)
    {
        $automezzo = DB::table('automezzi as a')
            ->join('associazioni as asso', 'a.idAssociazione', '=', 'asso.idAssociazione')
            ->join('anni as an',          'a.idAnno',         '=', 'an.idAnno')
            ->select([
                'a.idAutomezzo',
                'asso.Associazione',
                'an.Anno',
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
            ])
            ->where('a.idAutomezzo', $id)
            ->first();

        abort_if(!$automezzo, 404);

        return view('automezzi.show', compact('automezzo'));
    }

    /** GET /automezzi/{automezzo}/edit */
    public function edit($id)
    {
        $associazioni = DB::table('associazioni')->orderBy('Associazione')->get();
        $anni         = DB::table('anni')->orderBy('Anno')->get();
        $automezzo    = DB::table('automezzi')->where('idAutomezzo', $id)->first();

        abort_if(!$automezzo, 404);

        return view('automezzi.edit', compact('automezzo','associazioni','anni'));
    }

    /** PUT/PATCH /automezzi/{automezzo} */
    public function update(Request $request, $id)
    {
        $request->validate([
            'idAssociazione'                         => 'required|exists:associazioni,idAssociazione',
            'idAnno'                                 => 'required|exists:anni,idAnno',
            'Automezzo'                              => 'required|string|max:100',
            'Targa'                                  => 'nullable|string|max:20',
            'CodiceIdentificativo'                   => 'nullable|string|max:50',
            'AnnoPrimaImmatricolazione'              => 'nullable|digits:4',
            'Modello'                                => 'nullable|string|max:100',
            'TipoVeicolo'                            => 'nullable|string|max:50',
            'KmRiferimento'                          => 'nullable|integer',
            'KmTotali'                               => 'nullable|integer',
            'TipoCarburante'                         => 'nullable|string|max:30',
            'DataUltimaAutorizzazioneSanitaria'      => 'nullable|date',
            'DataUltimoCollaudo'                     => 'nullable|date',
        ]);

        DB::table('automezzi')
            ->where('idAutomezzo', $id)
            ->update([
                'idAssociazione'                         => $request->idAssociazione,
                'idAnno'                                 => $request->idAnno,
                'Automezzo'                              => $request->Automezzo,
                'Targa'                                  => $request->Targa,
                'CodiceIdentificativo'                   => $request->CodiceIdentificativo,
                'AnnoPrimaImmatricolazione'              => $request->AnnoPrimaImmatricolazione,
                'Modello'                                => $request->Modello,
                'TipoVeicolo'                            => $request->TipoVeicolo,
                'KmRiferimento'                          => $request->KmRiferimento,
                'KmTotali'                               => $request->KmTotali,
                'TipoCarburante'                         => $request->TipoCarburante,
                'DataUltimaAutorizzazioneSanitaria'      => $request->DataUltimaAutorizzazioneSanitaria,
                'DataUltimoCollaudo'                     => $request->DataUltimoCollaudo,
                'updated_at'                             => now(),
            ]);

        return redirect()
            ->route('automezzi.index')
            ->with('status', 'Automezzo aggiornato correttamente.');
    }

    /** DELETE /automezzi/{automezzo} */
    public function destroy($id)
    {
        DB::table('automezzi')->where('idAutomezzo', $id)->delete();

        return redirect()
            ->route('automezzi.index')
            ->with('status', 'Automezzo eliminato correttamente.');
    }
}

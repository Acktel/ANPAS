<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\AziendaSanitaria;
use App\Models\LottoAziendaSanitaria;

class ConfigurazioneLottiController extends Controller
{
    public function index(Request $request)
    {
        $idAziendaSanitaria = $request->integer('idAziendaSanitaria');
        $aziende = AziendaSanitaria::getAll(); // come già hai
        $lotti = LottoAziendaSanitaria::allWithAziende($idAziendaSanitaria);

        return view('configurazioni.aziende_sanitarie', compact('aziende', 'lotti', 'idAziendaSanitaria'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'idAziendaSanitaria' => ['required','integer','exists:aziende_sanitarie,idAziendaSanitaria'],
            'nomeLotto' => [
                'required','string','max:255',
                Rule::unique('aziende_sanitarie_lotti', 'nomeLotto')
                    ->where(fn($q) => $q->where('idAziendaSanitaria', $request->idAziendaSanitaria)),
            ],
        ]);

       LottoAziendaSanitaria::create($data);
        return back()->with('success', 'Lotto aggiunto.');
    }

    public function destroy(int $id)
    {
        LottoAziendaSanitaria::deleteById($id);
        return back()->with('success', 'LottoAziendaSanitaria eliminato.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AziendaSanitaria;
use App\Models\LottoAziendaSanitaria;

class ConfigurazioneLottiController extends Controller {
    public function index() {
        $aziende = AziendaSanitaria::getAll();
        $lotti = LottoAziendaSanitaria::getAllWithAziende();
        $idAziendaSanitaria = request('idAziendaSanitaria');
        return view('configurazioni.aziende_sanitarie', compact('aziende', 'lotti','idAziendaSanitaria'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'idAziendaSanitaria' => 'required|exists:aziende_sanitarie,idAziendaSanitaria',
            'nomeLotto' => 'required|string|max:255',
        ]);

        LottoAziendaSanitaria::createLotto($data);

        return back()->with('success', 'Lotto aggiunto.');
    }

    public function destroy($id) {
        LottoAziendaSanitaria::deleteLotto($id);

        return back()->with('success', 'Lotto eliminato.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Convenzione;
use App\Models\MezziSostitutivi;

class MezziSostitutiviController extends Controller {
    /**
     * GET /ajax/rot-sost/stato?idConvenzione=&anno=
     * Restituisce lo stato della convenzione (rotazione / sostitutivi)
     */
    public function stato(Request $req) {
        $idConv = (int) $req->integer('idConvenzione');
        $anno   = (int) ($req->integer('anno') ?: session('anno_riferimento', now()->year));
        if ($idConv <= 0) return response()->json(['ok' => false, 'message' => 'Convenzione mancante'], 422);

        $conv = Convenzione::getById($idConv);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Convenzione non trovata'], 404);

        if ((int)($conv->abilita_rot_sost ?? 0) !== 1) {
            return response()->json([
                'ok' => true,
                'modalita' => 'off',
                'percentuale' => null,
                'costo_fascia_oraria' => null,
                'costo_mezzi_sostitutivi' => null,
                'totale_netto' => null
            ]);
        }

        $titolare = Convenzione::getMezzoTitolare($idConv);
        if (!$titolare) {
            return response()->json([
                'ok' => true,
                'modalita' => 'off',
                'percentuale' => null,
                'costo_fascia_oraria' => null,
                'costo_mezzi_sostitutivi' => null,
                'totale_netto' => null
            ]);
        }

        $perc = (float)($titolare->percentuale ?? 0);
        $modalita = ($perc >= 98.0) ? 'sostitutivi' : 'rotazione';

        $stato = MezziSostitutivi::getStato($idConv, $anno);

        return response()->json([
            'ok' => true,
            'modalita' => $modalita,
            'percentuale' => $perc,
            'convenzione' => $conv->Convenzione ?? null,
            'costo_fascia_oraria' => $stato->costo_fascia_oraria,
            'costo_mezzi_sostitutivi' => $stato->costo_mezzi_sostitutivi,
            'totale_netto' => $stato->totale_netto,
        ]);
    }

    public function salva(Request $req) {
        $data = $req->validate([
            'idConvenzione'       => 'required|integer',
            'idAnno'              => 'required|integer',
            'costo_fascia_oraria' => 'required|numeric|min:0',
        ]);

        $idConv = (int)$data['idConvenzione'];
        $anno   = (int)$data['idAnno'];
        $costo  = (float)$data['costo_fascia_oraria'];

        $conv = Convenzione::getById($idConv);
        if (!$conv || (int)($conv->abilita_rot_sost ?? 0) !== 1) {
            $msg = 'Funzione disabilitata per questa convenzione.';
            return $req->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 422)
                : redirect()->route('riepilogo.costi')->with('error', $msg);
        }

        $titolare = Convenzione::getMezzoTitolare($idConv);
        if (!$titolare || (float)$titolare->percentuale < 98.0) {
            $msg = 'La convenzione è in Rotazione: non è previsto il calcolo mezzi sostitutivi.';
            return $req->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 422)
                : redirect()->route('riepilogo.costi')->with('error', $msg);
        }

        MezziSostitutivi::upsertCosto($idConv, $anno, $costo);

        return $req->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('riepilogo.costi')->with('success', 'Costo fascia oraria aggiornato correttamente.');
    }


    public function edit(int $idConvenzione, Request $req) {
        $anno = (int)($req->integer('anno') ?: session('anno_riferimento', now()->year));
        $conv = Convenzione::getById($idConvenzione);
        abort_unless($conv, 404);

        $titolare = Convenzione::getMezzoTitolare($idConvenzione);
        $perc = $titolare ? (float)$titolare->percentuale : null;
        $isSost = $conv && (int)($conv->abilita_rot_sost ?? 0) === 1 && $perc !== null && $perc >= 98.0;

        $row = MezziSostitutivi::getByConvenzioneAnno($idConvenzione, $anno);
        $costo = $row ? (float)$row->costo_fascia_oraria : 0;

        return view('mezzi_sostitutivi.edit', [
            'idConvenzione'   => $idConvenzione,
            'anno'            => $anno,
            'costo'           => $costo,
            'nomeConvenzione' => $conv->Convenzione ?? null,
            'percTitolare'    => $perc,
            'isSostitutivi'   => $isSost,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Convenzione;
use App\Models\MezziSostitutivi;
use App\Services\RipartizioneCostiService;

class MezziSostitutiviController extends Controller
{
    /**
     * GET /ajax/rot-sost/stato?idConvenzione=&anno=
     * Restituisce lo stato (rotazione / sostitutivi) + i costi.
     */
    public function stato(Request $req)
    {
        $idConv = (int) $req->integer('idConvenzione');
        $anno   = (int) ($req->integer('anno') ?: session('anno_riferimento', now()->year));

        if ($idConv <= 0) {
            return response()->json(['ok' => false, 'message' => 'Convenzione mancante'], 422);
        }

        $conv = Convenzione::getById($idConv);
        if (!$conv) {
            return response()->json(['ok' => false, 'message' => 'Convenzione non trovata'], 404);
        }

        // Funzionalità disattiva per la convenzione
        if ((int)($conv->abilita_rot_sost ?? 0) !== 1) {
            return response()->json([
                'ok'          => true,
                'modalita'    => 'off',
                'convenzione' => $conv->Convenzione,
            ]);
        }

        // Mezzo titolare
        $titolare = Convenzione::getMezzoTitolare($idConv);
        if (!$titolare) {
            return response()->json([
                'ok'          => true,
                'modalita'    => 'no-titolare',
                'convenzione' => $conv->Convenzione,
            ]);
        }

        // Regola: SOLO % tradizionale
        $percTrad = (float) ($titolare->percent_trad ?? 0.0);
        $modalita = $percTrad >= 98.0 ? 'sostitutivi' : 'rotazione';

        // Costi (solo se sostitutivi)
        $costoFascia = 0.0;
        $costoSost   = 0.0;

        if ($modalita === 'sostitutivi') {
            // 1) Importo fascia oraria
            $row = MezziSostitutivi::getByConvenzioneAnno($idConv, $anno);
            $costoFascia = $row ? (float)$row->costo_fascia_oraria : 0.0;

            // 2) Costo reale mezzi sostitutivi (dal SERVICE)
            $stato = MezziSostitutivi::getStato($idConv, $anno);
            $costoSost = $stato->costo_mezzi_sostitutivi;
        }

        return response()->json([
            'ok'                      => true,
            'modalita'                => $modalita,
            'convenzione'             => $conv->Convenzione,
            'percent_trad'            => $percTrad,
            'percent_rot'             => (float) ($titolare->percent_rot ?? 0.0),
            'km_titolare'             => (float) ($titolare->km_titolare ?? 0.0),
            'km_tot_conv'             => (float) ($titolare->km_totali_conv ?? 0.0),
            'km_tot_mezzo'            => (float) ($titolare->km_totali_mezzo ?? 0.0),
            'costo_fascia_oraria'     => $costoFascia,
            'costo_mezzi_sostitutivi' => $costoSost,
            'differenza_netto'        => max(0.0, $costoSost - $costoFascia),
        ]);
    }

    /**
     * Salva il costo fascia oraria.
     */
    public function salva(Request $req)
    {
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
            return $req->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Funzione disabilitata per questa convenzione.'], 422)
                : redirect()->route('riepilogo.costi')->with('error', 'Funzione disabilitata per questa convenzione.');
        }

        // Verifica regime "sostitutivi"
        $titolare = Convenzione::getMezzoTitolare($idConv);
        $percTrad = $titolare ? (float)$titolare->percent_trad : null;

        if ($percTrad === null || $percTrad < 98.0) {
            return $req->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'La convenzione è in Rotazione.'], 422)
                : redirect()->route('riepilogo.costi')->with('error', 'La convenzione è in Rotazione.');
        }

        // Salva
        MezziSostitutivi::upsertCosto($idConv, $anno, $costo);

        return $req->expectsJson()
            ? response()->json(['ok' => true])
            : redirect()->route('riepilogo.costi')->with('success', 'Costo fascia oraria aggiornato correttamente.');
    }

    /**
     * Vista edit singola convenzione.
     */
    public function edit(int $idConvenzione, Request $req)
    {
        $anno = (int)($req->integer('anno') ?: session('anno_riferimento', now()->year));
        $conv = Convenzione::getById($idConvenzione);
        abort_unless($conv, 404);

        $titolare = Convenzione::getMezzoTitolare($idConvenzione);
        $percTrad = $titolare ? (float)$titolare->percent_trad : null;

        $isSost = $conv
            && (int)$conv->abilita_rot_sost === 1
            && $percTrad !== null
            && $percTrad >= 98.0;

        $row = MezziSostitutivi::getByConvenzioneAnno($idConvenzione, $anno);
        $costo = $row ? (float)$row->costo_fascia_oraria : 0.0;

        return view('mezzi_sostitutivi.edit', [
            'idConvenzione'   => $idConvenzione,
            'anno'            => $anno,
            'costo'           => $costo,
            'nomeConvenzione' => $conv->Convenzione,
            'percTitolare'    => $percTrad,
            'isSostitutivi'   => $isSost,
        ]);
    }
}

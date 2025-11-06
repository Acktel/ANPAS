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
    // App/Http/Controllers/MezziSostitutiviController.php

    public function stato(Request $req) {
        $idConv = (int) $req->integer('idConvenzione');
        $anno   = (int) ($req->integer('anno') ?: session('anno_riferimento', now()->year));
        if ($idConv <= 0) return response()->json(['ok' => false, 'message' => 'Convenzione mancante'], 422);

        $conv = Convenzione::getById($idConv);
        if (!$conv) return response()->json(['ok' => false, 'message' => 'Convenzione non trovata'], 404);

        // se la funzionalità non è abilitata per la convenzione, esci subito
        if ((int)($conv->abilita_rot_sost ?? 0) !== 1) {
            return response()->json([
                'ok'          => true,
                'modalita'    => 'off',
                'convenzione' => $conv->Convenzione ?? null,
            ]);
        }

        // mezzo titolare + percentuali (devono includere percent_trad)
        $titolare = Convenzione::getMezzoTitolare($idConv);
        if (!$titolare) {
            return response()->json([
                'ok'          => true,
                'modalita'    => 'no-titolare',
                'convenzione' => $conv->Convenzione ?? null,
            ]);
        }

        // REGOLA: si usa SOLO la % tradizionale
        $percTrad = (float) ($titolare->percent_trad ?? 0.0);
        $modalita = $percTrad >= 98.0 ? 'sostitutivi' : 'rotazione';

        // Valori economici (solo se davvero "sostitutivi")
        $costoFascia = 0.0;
        $costoSost   = 0.0;
        if ($modalita === 'sostitutivi') {
            // costo fascia oraria da tabella mezzi_sostitutivi
            $row = MezziSostitutivi::getByConvenzioneAnno($idConv, $anno);
            $costoFascia = $row ? (float)$row->costo_fascia_oraria : 0.0;

            // costo netto sostitutivi calcolato dalla ripartizione totale (service)
            $netByConv = \App\Services\RipartizioneCostiService::costoNettoMezziSostitutiviByConvenzione(
                (int)$conv->idAssociazione,
                $anno
            );
            $costoSost = (float)($netByConv[$idConv] ?? 0.0);
        }

        return response()->json([
            'ok'              => true,
            'modalita'        => $modalita,                 // 'sostitutivi' | 'rotazione' | 'no-titolare' | 'off'
            'convenzione'     => $conv->Convenzione ?? null,
            // debug/info
            'percent_trad'    => $percTrad,
            'percent_rot'     => (float) ($titolare->percent_rot ?? 0.0),
            'km_titolare'     => (float) ($titolare->km_titolare ?? 0.0),
            'km_tot_conv'     => (float) ($titolare->km_totali_conv ?? 0.0),
            'km_tot_mezzo'    => (float) ($titolare->km_totali_mezzo ?? 0.0),
            'costo_fascia_oraria'     => $costoFascia,
            'costo_mezzi_sostitutivi' => $costoSost,
            'differenza_netto'        => max(0.0, $costoSost - $costoFascia),
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
        $percTrad = $titolare ? (float)$titolare->percent_trad : null;

        if ($percTrad === null || $percTrad < 98.0) {
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

        // Recupera mezzo titolare con le due percentuali
        $titolare = Convenzione::getMezzoTitolare($idConvenzione);

        // Usa SEMPRE la % tradizionale come riferimento
        $percTrad = $titolare ? (float)$titolare->percent_trad : null;

        // Regime "mezzi sostitutivi" = abilitato + % trad >= 98
        $isSost = $conv
            && (int)($conv->abilita_rot_sost ?? 0) === 1
            && $percTrad !== null
            && $percTrad >= 98.0;

        // Costo già salvato (se esiste)
        $row = MezziSostitutivi::getByConvenzioneAnno($idConvenzione, $anno);
        $costo = $row ? (float)$row->costo_fascia_oraria : 0.0;

        return view('mezzi_sostitutivi.edit', [
            'idConvenzione'   => $idConvenzione,
            'anno'            => $anno,
            'costo'           => $costo,
            'nomeConvenzione' => $conv->Convenzione ?? null,
            'percTitolare'    => $percTrad,    
            'isSostitutivi'   => $isSost,       
        ]);
    }
}

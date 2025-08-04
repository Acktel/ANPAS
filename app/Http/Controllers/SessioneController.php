<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SessioneController extends Controller {
    /**
     * Imposta l'associazione selezionata nella sessione
     */
    public function setAssociazione(Request $request) {
        $request->validate([
            'idAssociazione' => 'required|integer|exists:associazioni,IdAssociazione',
        ]);

        session(['associazione_selezionata' => $request->idAssociazione]);

        return redirect()->back(); // <-- questa è la chiave
    }

    /**
     * Imposta l'anno di riferimento nella sessione
     */
    public function setAnno(Request $request) {
        $request->validate([
            'anno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        session(['anno_riferimento' => $request->anno]);

        return response()->json(['success' => true]);
    }
}

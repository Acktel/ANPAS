<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\RiepilogoController;

Route::middleware(['auth'])->group(function () {

    /* ===== Utilità sessione/anno ===== */

    Route::post('/cambia-anno', function (Request $request) {
        $request->validate([
            'anno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);
        session(['anno_riferimento' => (int) $request->input('anno')]);
        return back();
    })->name('cambia.anno');

    Route::post('/sessione/set-convenzione', function (Request $request) {
        $request->validate(['idConvenzione' => 'nullable']); // 'TOT' oppure int
        $val = $request->input('idConvenzione');
        session(['convenzione_selezionata' => $val === 'TOT' ? 'TOT' : (int) $val]);
        return response()->json(['ok' => true]);
    })->name('sessione.setConvenzione');


    /* ===== AJAX convenzioni per associazione+anno ===== */

    Route::get(
        '/ajax/convenzioni-by-associazione/{idAssociazione}',
        [RiepilogoController::class, 'convenzioniByAssociazione']
    )->whereNumber('idAssociazione')
        ->name('riepiloghi.convenzioniByAssociazione');

    Route::get(
        '/associazioni/{idAssociazione}/convenzioni',
        [RiepilogoController::class, 'convenzioniByAssociazione']
    )->whereNumber('idAssociazione')
        ->name('associazioni.convenzioni');


    /* ===== Data per DataTables tipologia 1 ===== */

    Route::get('/riepiloghi/data', [RiepilogoController::class, 'getData'])
        ->name('riepiloghi.data');

    Route::get('/riepiloghi/valori', [RiepilogoController::class, 'getData'])
        ->name('riepiloghi.valori');


    /* ===== Salvataggi puntuali del preventivo ===== */

    Route::post('/riepiloghi/save-preventivo', [RiepilogoController::class, 'savePreventivo'])
        ->name('riepiloghi.savePreventivo');

    Route::post('/riepiloghi/valori', [RiepilogoController::class, 'savePreventivo'])
        ->name('riepiloghi.valori.store');


    /* ===== Operazioni su singola riga (PRIMA delle generiche) ===== */
    Route::get('/riepiloghi/riga/ensure-edit', [RiepilogoController::class, 'ensureAndRedirectToEdit'])
        ->name('riepiloghi.riga.ensureEdit');

    // DELETE riga per CHIAVI (idRiepilogo + voce_id + idConvenzione)
    Route::delete('/riepiloghi/riga/destroy-by-keys', [RiepilogoController::class, 'destroyRigaByKeys'])
        ->name('riepiloghi.riga.destroyByKeys');


    Route::get('/riepiloghi/riga/{id}/edit', [RiepilogoController::class, 'editRiga'])
        ->whereNumber('id')
        ->name('riepiloghi.riga.edit');

    Route::put('/riepiloghi/riga/{id}', [RiepilogoController::class, 'updateRiga'])
        ->whereNumber('id')
        ->name('riepiloghi.riga.update');

    Route::delete('/riepiloghi/riga/{id}', [RiepilogoController::class, 'destroyRiga'])
        ->whereNumber('id')
        ->name('riepiloghi.riga.destroy');


    /* ===== Edit TOTALE per voce consentita ===== */

    // Pagina di edit TOTALE (usata quando la select è su "TOT")
    Route::get(
        '/riepiloghi/{riepilogo}/voce/{voce}/tot/edit',
        [RiepilogoController::class, 'editVoceTotale']
    )->whereNumber('riepilogo')->whereNumber('voce')
        ->name('riepiloghi.voce.tot.edit');

    // Applica il valore TOTALE a tutte le convenzioni
    Route::post('/riepiloghi/voce/apply-tot', [RiepilogoController::class, 'applyVoceTotale'])
        ->name('riepiloghi.voce.applyTot');


    /* ===== Rotte “generiche” del riepilogo ===== */

    Route::get('/riepiloghi', [RiepilogoController::class, 'index'])
        ->name('riepiloghi.index');

    Route::get('/riepiloghi/create', [RiepilogoController::class, 'create'])
        ->name('riepiloghi.create');

    Route::post('/riepiloghi', [RiepilogoController::class, 'store'])
        ->name('riepiloghi.store');

    Route::get('/riepiloghi/{riepilogo}', [RiepilogoController::class, 'show'])
        ->whereNumber('riepilogo')
        ->name('riepiloghi.show');

    Route::get('/riepiloghi/{riepilogo}/edit', [RiepilogoController::class, 'edit'])
        ->whereNumber('riepilogo')
        ->name('riepiloghi.edit');

    Route::put('/riepiloghi/{riepilogo}', [RiepilogoController::class, 'update'])
        ->whereNumber('riepilogo')
        ->name('riepiloghi.update');

    Route::delete('/riepiloghi/{riepilogo}', [RiepilogoController::class, 'destroy'])
        ->whereNumber('riepilogo')
        ->name('riepiloghi.destroy');
});

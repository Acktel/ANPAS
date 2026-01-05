<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RiepilogoCostiController;

Route::middleware(['auth'])->prefix('riepilogo-costi')->group(function () {

    // Pagina principale riepilogo costi
    Route::get('/', [RiepilogoCostiController::class, 'index'])
        ->name('riepilogo.costi');

    // Sezioni dinamiche (AJAX) -> carica le voci per tipologia
    Route::get('/sezione/{idTipologia}', [RiepilogoCostiController::class, 'getSezione'])
        ->whereNumber('idTipologia')
        ->name('riepilogo.costi.sezione');

    // Forza creazione riga per chiavi (associazione+anno+convenzione+voce) e vai in edit
    Route::get('/ensure-edit', [RiepilogoCostiController::class, 'ensureAndEditByKeys'])
        ->name('riepilogo.costi.ensureEdit');

    // Edit singola voce (by rigaId)
    Route::get('/voce/{id}/edit', [RiepilogoCostiController::class, 'edit'])
        ->whereNumber('id')
        ->name('riepilogo.costi.edit');

    // Update singola voce (by voceId)
    Route::put('/voce/{voceId}', [RiepilogoCostiController::class, 'update'])
        ->whereNumber('voceId')
        ->name('riepilogo.costi.update');

    // Inline save preventivo (AJAX)
    Route::post('/save-preventivo', [RiepilogoCostiController::class, 'savePreventivo'])
        ->name('riepilogo.costi.savePreventivo');

    // (opzionale) check duplicazione dall’anno precedente
    Route::get('/check-duplicazione', [RiepilogoCostiController::class, 'checkDuplicazione'])
        ->name('riepilogo.costi.checkDuplicazione');

    // Edit/Update unificato per “UTENZE TELEFONICHE”
    Route::get('/edit-telefonia', [RiepilogoCostiController::class, 'editTelefonia'])
        ->name('riepilogo.costi.edit.telefonia');

    Route::post('/update-telefonia', [RiepilogoCostiController::class, 'updateTelefonia'])
        ->name('riepilogo.costi.update.telefonia');

    // Edit/Update unificato per “Formazione (A + DAE + RDAE)” = merge 6010+6011
    Route::get('/edit-formazione', [RiepilogoCostiController::class, 'editFormazione'])
        ->name('riepilogo.costi.edit.formazione');

    Route::post('/update-formazione', [RiepilogoCostiController::class, 'updateFormazione'])
        ->name('riepilogo.costi.update.formazione');

    // Bulk: edit + update preventivi per sezione
    Route::get('/sezione/{sezione}/edit-preventivi', [RiepilogoCostiController::class, 'editPreventiviSezione'])
        ->whereNumber('sezione')
        ->name('riepilogo.costi.editPreventiviSezione');

    Route::post('/sezione/{sezione}/update-preventivi', [RiepilogoCostiController::class, 'updatePreventiviSezione'])
        ->whereNumber('sezione')
        ->name('riepilogo.costi.updatePreventiviSezione');

    Route::get('/riepilogo-costi/summary', [RiepilogoCostiController::class, 'getSummary'])
        ->name('riepilogo.costi.summary');

});

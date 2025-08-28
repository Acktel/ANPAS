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

    // Edit singola voce (by idVoceConfig)
    Route::get('/voce/{id}/edit', [RiepilogoCostiController::class, 'edit'])
        ->whereNumber('id')
        ->name('riepilogo.costi.edit');

    // Update singola voce
    Route::put('/voce/{id}', [RiepilogoCostiController::class, 'update'])
        ->whereNumber('id')
        ->name('riepilogo.costi.update');

    // Inline save preventivo (AJAX)
    Route::post('/save-preventivo', [RiepilogoCostiController::class, 'savePreventivo'])
        ->name('riepilogo.costi.savePreventivo');

    // (opzionale) check duplicazione dall’anno precedente
    Route::get('/check-duplicazione', [RiepilogoCostiController::class, 'checkDuplicazione'])
        ->name('riepilogo.costi.checkDuplicazione');
});

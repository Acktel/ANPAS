<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DistintaImputazioneCostiController;

Route::prefix('distinta-imputazione-costi')->name('distinta.imputazione.')->group(function () {
    // Dashboard + dati
    Route::get('/', [DistintaImputazioneCostiController::class, 'index'])->name('index');
    Route::get('/data', [DistintaImputazioneCostiController::class, 'getData'])->name('data');

    // === Form "esploso" per tutte le voci della sezione (una convenzione selezionata) ===
    Route::get('/create/{sezione}', [DistintaImputazioneCostiController::class, 'create'])
        ->whereNumber('sezione')->name('create');
    Route::post('/store-bulk', [DistintaImputazioneCostiController::class, 'storeBulk'])
        ->name('storeBulk');

    // === Edit griglia sezione (voce x convenzione) — opzionale ===
    Route::get('/sezioni/{sezione}/edit', [DistintaImputazioneCostiController::class, 'editSezione'])
        ->whereNumber('sezione')->name('editSezione');
    Route::put('/sezioni/{sezione}', [DistintaImputazioneCostiController::class, 'updateSezione'])
        ->whereNumber('sezione')->name('updateSezione');

    // === SOLO "Importo Totale da Bilancio Consuntivo" (giallo) per sezione ===
    Route::get('/sezioni/{sezione}/bilancio/edit', [DistintaImputazioneCostiController::class, 'editBilancioSezione'])
        ->whereNumber('sezione')->name('editBilancio');
    Route::put('/sezioni/{sezione}/bilancio', [DistintaImputazioneCostiController::class, 'updateBilancioSezione'])
        ->whereNumber('sezione')->name('updateBilancio');

    // === API/legacy ===
    Route::post('/salva-costo-diretto', [DistintaImputazioneCostiController::class, 'salvaCostoDiretto'])
        ->name('salvaCostoDiretto');
    Route::get('/personale-per-convenzione', [DistintaImputazioneCostiController::class, 'personalePerConvenzione'])
        ->name('personale_per_convenzione');

    // (vecchie rotte CRUD singola riga: tienile commentate se non servono più)
    // Route::post('/store', [DistintaImputazioneCostiController::class, 'store'])->name('store');
    // Route::get('/{id}/edit', [DistintaImputazioneCostiController::class, 'edit'])->name('edit');
    // Route::put('/{id}', [DistintaImputazioneCostiController::class, 'update'])->name('update');
    // Route::delete('/{id}', [DistintaImputazioneCostiController::class, 'destroy'])->name('destroy');
});

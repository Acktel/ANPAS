<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RiepilogoCostiController;

Route::middleware(['auth'])->prefix('riepilogo-costi')->group(function () {

    // 📄 Pagina principale riepilogo costi
    Route::get('/', [RiepilogoCostiController::class, 'index'])
        ->name('riepilogo.costi');

    // 📊 Sezioni dinamiche (per DataTable AJAX)
    Route::get('/sezione/{idTipologia}', [RiepilogoCostiController::class, 'getSezione'])
        ->whereNumber('idTipologia')
        ->name('riepilogo.costi.sezione');

    // ➕ Form creazione nuova voce
    Route::get('/sezione/{idTipologia}/create', [RiepilogoCostiController::class, 'create'])
        ->whereNumber('idTipologia')
        ->name('riepilogo.costi.create');

    // 💾 Salvataggio nuova voce
    Route::post('/sezione/{idTipologia}', [RiepilogoCostiController::class, 'store'])
        ->whereNumber('idTipologia')
        ->name('riepilogo.costi.store');

    // ✏️ Form modifica voce
    Route::get('/riga/{id}/edit', [RiepilogoCostiController::class, 'edit'])
        ->whereNumber('id')
        ->name('riepilogo.costi.edit');

    // 🔁 Aggiornamento voce esistente
    Route::put('/riga/{id}', [RiepilogoCostiController::class, 'update'])
        ->whereNumber('id')
        ->name('riepilogo.costi.update');

    // ❌ Eliminazione voce
    Route::delete('/riga/{id}', [RiepilogoCostiController::class, 'destroy'])
        ->whereNumber('id')
        ->name('riepilogo.costi.destroy');

    // 📥 Importazione da anno precedente (per singola tipologia)
    Route::post('/sezione/{idTipologia}/import', [RiepilogoCostiController::class, 'importFromPreviousYear'])
        ->whereNumber('idTipologia')
        ->name('riepilogo.costi.import');

    // ✅ Check duplicazione voci da anno precedente (intero riepilogo)
    Route::get('/check-duplicazione', [RiepilogoCostiController::class, 'checkDuplicazione'])
        ->name('riepilogo.costi.checkDuplicazione');

    // 🔁 Duplicazione voci da anno precedente
    Route::post('/duplica', [RiepilogoCostiController::class, 'duplicaDaAnnoPrecedente'])
        ->name('riepilogo.costi.duplica');
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DistintaImputazioneCostiController;

Route::prefix('distinta-imputazione-costi')->name('distinta.imputazione.')->group(function () {
    // Vista principale
    Route::get('/', [DistintaImputazioneCostiController::class, 'index'])->name('index');

    // API per ottenere i dati per la datatable
    Route::get('/data', [DistintaImputazioneCostiController::class, 'getData'])->name('data');

    // Aggiunta costi diretti per sezione (form)
    Route::get('/create/{sezione}', [DistintaImputazioneCostiController::class, 'create'])->name('create');

    // Salvataggio costi diretti multipli per voce/convenzione
    Route::post('/store', [DistintaImputazioneCostiController::class, 'store'])->name('store');

    // Modifica costi diretti esistenti (se prevista)
    Route::get('/{id}/edit', [DistintaImputazioneCostiController::class, 'edit'])->name('edit');
    Route::put('/{id}', [DistintaImputazioneCostiController::class, 'update'])->name('update');

    // Eliminazione (se prevista)
    Route::delete('/{id}', [DistintaImputazioneCostiController::class, 'destroy'])->name('destroy');
});

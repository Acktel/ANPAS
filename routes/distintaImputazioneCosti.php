<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DistintaImputazioneCostiController;

Route::prefix('distinta-imputazione-costi')->name('distinta.imputazione.')->group(function () {
    // Vista principale
    Route::get('/', [DistintaImputazioneCostiController::class, 'index'])->name('index');

    // API per ottenere i dati (datatable dinamica)
    Route::get('/data', [DistintaImputazioneCostiController::class, 'getData'])->name('data');

    // Create voce (costi diretti)
    Route::get('/create/{sezione}', [DistintaImputazioneCostiController::class, 'create'])->name('create');
    Route::post('/', [DistintaImputazioneCostiController::class, 'store'])->name('store');

    // Edit voce
    Route::get('/{id}/edit', [DistintaImputazioneCostiController::class, 'edit'])->name('edit');
    Route::put('/{id}', [DistintaImputazioneCostiController::class, 'update'])->name('update');

    // Destroy voce (se previsto)
    Route::delete('/{id}', [DistintaImputazioneCostiController::class, 'destroy'])->name('destroy');
});

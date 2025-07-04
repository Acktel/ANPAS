<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RapportiRicaviController;

Route::middleware(['auth'])->prefix('rapporti-ricavi')->group(function () {

    // Pagina principale
    Route::get('/', [RapportiRicaviController::class, 'index'])
        ->name('rapporti-ricavi.index');

    // Datatable (AJAX)
    Route::get('/datatable', [RapportiRicaviController::class, 'getData'])
        ->name('rapporti-ricavi.datatable');

    // Form creazione
    Route::get('/create', [RapportiRicaviController::class, 'create'])
        ->name('rapporti-ricavi.create');

    // Salvataggio nuovo
    Route::post('/', [RapportiRicaviController::class, 'store'])
        ->name('rapporti-ricavi.store');

    // Visualizzazione singolo
    Route::get('/{id}', [RapportiRicaviController::class, 'show'])
        ->whereNumber('id')
        ->name('rapporti-ricavi.show');

    // Modifica esistente
    Route::get('/{id}/edit', [RapportiRicaviController::class, 'edit'])
        ->whereNumber('id')
        ->name('rapporti-ricavi.edit');

    // Salvataggio modifica
    Route::put('/{id}', [RapportiRicaviController::class, 'update'])
        ->whereNumber('id')
        ->name('rapporti-ricavi.update');

    // Eliminazione
    Route::delete('/{id}', [RapportiRicaviController::class, 'destroy'])
        ->whereNumber('id')
        ->name('rapporti-ricavi.destroy');
});

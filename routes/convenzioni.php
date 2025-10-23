<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConvenzioniController;

Route::middleware(['auth'])->group(function () {
    // Custom routes 
    Route::post('/convenzioni/{id}/set-rot-sost', [ConvenzioniController::class, 'setRotSost'])
        ->name('convenzioni.setRotSost');

    Route::get('/convenzioni/check-duplicazione', [ConvenzioniController::class, 'checkDuplicazioneDisponibile'])
        ->name('convenzioni.checkDuplicazione');

    Route::post('/convenzioni/duplica', [ConvenzioniController::class, 'duplicaAnnoPrecedente'])
        ->name('convenzioni.duplica');

    Route::post('/convenzioni/riordina', [ConvenzioniController::class, 'riordina'])
        ->name('convenzioni.riordina');

    // Route RESTful standard (create, edit, update, destroy ecc.)
    Route::resource('convenzioni', ConvenzioniController::class)->except(['show']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DipendenteController;

Route::middleware(['auth'])->group(function () {
    // Data di base
    Route::get('dipendenti/data', [DipendenteController::class, 'getData'])
        ->name('dipendenti.data');

    // Pagine/endpoint per QUALIFICA (dinamico)
    Route::get('dipendenti/qualifica/{id}', [DipendenteController::class, 'byQualifica'])
        ->name('dipendenti.byQualifica');

    Route::get('dipendenti/qualifica/{id}/data', [DipendenteController::class, 'byQualificaData'])
        ->name('dipendenti.byQualifica.data');

    // (Opzionali) viste shortcut
    Route::get('dipendenti/autisti', [DipendenteController::class, 'autisti'])->name('dipendenti.autisti');
    Route::get('dipendenti/autisti/data', [DipendenteController::class, 'autistiData'])->name('dipendenti.autisti.data');

    Route::get('dipendenti/amministrativi', [DipendenteController::class, 'amministrativi'])->name('dipendenti.amministrativi');
    
    // Duplicazione anno
    Route::post('dipendenti/duplica-precedente', [DipendenteController::class, 'duplicaAnnoPrecedente'])
        ->name('dipendenti.duplica');
    Route::get('dipendenti/check-duplicazione', [DipendenteController::class, 'checkDuplicazioneDisponibile'])
        ->name('dipendenti.checkDuplicazione');

    // CRUD
    Route::resource('dipendenti', DipendenteController::class);
});


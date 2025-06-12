<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConvenzioniController;

Route::middleware(['auth'])->group(function () {
    // Duplicazione API protette
    Route::get('/convenzioni/check-duplicazione', [ConvenzioniController::class, 'checkDuplicazioneDisponibile'])->name('convenzioni.checkDuplicazione');
    Route::post('/convenzioni/duplica', [ConvenzioniController::class, 'duplicaAnnoPrecedente'])->name('convenzioni.duplica');

    // Resource standard
    Route::resource('convenzioni', ConvenzioniController::class);
});
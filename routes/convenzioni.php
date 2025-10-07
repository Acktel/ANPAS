<?php

// routes/web.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConvenzioniController;

Route::middleware(['auth'])->group(function () {
    Route::get('/convenzioni/check-duplicazione', [ConvenzioniController::class, 'checkDuplicazioneDisponibile'])->name('convenzioni.checkDuplicazione');
    Route::post('/convenzioni/duplica', [ConvenzioniController::class, 'duplicaAnnoPrecedente'])->name('convenzioni.duplica');
    Route::post('/convenzioni/riordina', [ConvenzioniController::class, 'riordina'])->name('convenzioni.riordina');

    Route::resource('convenzioni', ConvenzioniController::class)->except(['show']);
});
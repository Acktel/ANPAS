<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DipendenteController;

Route::middleware(['auth'])->group(function () {

    // ✅ Rotta AJAX per DataTable
    Route::get('dipendenti/data', [DipendenteController::class, 'getData'])
         ->name('dipendenti.data');
     Route::get('dipendenti/altro/data', [DipendenteController::class, 'altroData'])
     ->name('dipendenti.altro.data');

    // ✅ Rotte filtrate (autisti e altro)
    Route::get('dipendenti/autisti', [DipendenteController::class, 'autisti'])
         ->name('dipendenti.autisti');
    Route::get('dipendenti/altro',   [DipendenteController::class, 'altro'])
         ->name('dipendenti.altro');

    // ✅ Rotta per duplicare i dipendenti dall'anno precedente
    Route::post('dipendenti/duplica-precedente', [DipendenteController::class, 'duplicaAnnoPrecedente'])
         ->name('dipendenti.duplica');

     Route::get('dipendenti/check-duplicazione', [DipendenteController::class, 'checkDuplicazioneDisponibile'])
    ->name('dipendenti.checkDuplicazione');

    // ✅ Resource completo (index, create, store, show, edit, update, destroy)
    Route::resource('dipendenti', DipendenteController::class);
});

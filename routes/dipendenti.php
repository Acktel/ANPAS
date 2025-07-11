<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DipendenteController;

Route::middleware(['auth'])->group(function () {

     // ✅ Rotte AJAX per DataTable
     Route::get('dipendenti/data', [DipendenteController::class, 'getData'])
          ->name('dipendenti.data');

     Route::get('dipendenti/altro/data', [DipendenteController::class, 'altroData'])
          ->name('dipendenti.altro.data');

     Route::get('dipendenti/amministrativi/data', [DipendenteController::class, 'amministrativiData'])
          ->name('dipendenti.amministrativi.data'); // 🔧 QUESTA MANCAVA

     // ✅ Pagine filtrate
     Route::get('dipendenti/autisti', [DipendenteController::class, 'autisti'])
          ->name('dipendenti.autisti');

     Route::get('dipendenti/altro', [DipendenteController::class, 'altro'])
          ->name('dipendenti.altro');

     Route::get('dipendenti/amministrativi', [DipendenteController::class, 'amministrativi'])
          ->name('dipendenti.amministrativi');
     Route::get('dipendenti/autisti', [DipendenteController::class, 'autisti'])
          ->name('dipendenti.autisti');

     Route::get('dipendenti/autisti/data', [DipendenteController::class, 'autistiData'])
          ->name('dipendenti.autisti.data');

     // ✅ Duplicazione dall’anno precedente
     Route::post('dipendenti/duplica-precedente', [DipendenteController::class, 'duplicaAnnoPrecedente'])
          ->name('dipendenti.duplica');

     Route::get('dipendenti/check-duplicazione', [DipendenteController::class, 'checkDuplicazioneDisponibile'])
          ->name('dipendenti.checkDuplicazione');

     // ✅ CRUD completo
     Route::resource('dipendenti', DipendenteController::class);
});

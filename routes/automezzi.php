<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutomezziController;

Route::middleware(['auth'])->group(function () {

     // 🔁 Duplicazione da anno precedente
     Route::get('automezzi/check-duplicazione', [AutomezziController::class, 'checkDuplicazioneDisponibile'])
          ->name('automezzi.checkDuplicazione');

     Route::post('automezzi/duplica-precedente', [AutomezziController::class, 'duplicaAnnoPrecedente'])
          ->name('automezzi.duplica');
          
     Route::get('/automezzi-dt', [AutomezziController::class, 'datatable'])->name('automezzi.datatable');

     // 📦 Tutte le rotte CRUD standard
     Route::resource('automezzi', AutomezziController::class);
});

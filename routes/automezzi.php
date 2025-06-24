<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutomezziController;
use App\Http\Controllers\KmPercorsiController;

Route::middleware(['auth'])->group(function () {

     // 🔁 Duplicazione da anno precedente
     Route::get('automezzi/check-duplicazione', [AutomezziController::class, 'checkDuplicazioneDisponibile'])
          ->name('automezzi.checkDuplicazione');

     Route::post('automezzi/duplica-precedente', [AutomezziController::class, 'duplicaAnnoPrecedente'])
          ->name('automezzi.duplica');

     Route::get('/automezzi-dt', [AutomezziController::class, 'datatable'])
          ->name('automezzi.datatable');

     // 📦 Rotte complete per gestione km-percorsi
     Route::prefix('km-percorsi')->name('km-percorsi.')->group(function () {
          Route::get('/', [KmPercorsiController::class, 'index'])->name('index');
          Route::get('/datatable', [KmPercorsiController::class, 'getData'])->name('datatable');
          Route::get('/create', [KmPercorsiController::class, 'create'])->name('create');
          Route::post('/', [KmPercorsiController::class, 'store'])->name('store');

          Route::get('/{id}', [KmPercorsiController::class, 'show'])
               ->whereNumber('id')->name('show');

          Route::get('/{id}/edit', [KmPercorsiController::class, 'edit'])
               ->whereNumber('id')->name('edit');

          Route::put('/{id}', [KmPercorsiController::class, 'update'])
               ->whereNumber('id')->name('update');

          Route::delete('/{id}', [KmPercorsiController::class, 'destroy'])
               ->whereNumber('id')->name('destroy');
     });


     // 📦 CRUD automezzi
     Route::resource('automezzi', AutomezziController::class);
});

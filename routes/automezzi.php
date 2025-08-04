<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutomezziController;
use App\Http\Controllers\KmPercorsiController;
use App\Http\Controllers\ServiziSvoltiController;


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

     // 📦 Rotte complete per gestione servizi svolti per convenzione
     Route::prefix('servizi-svolti')->name('servizi-svolti.')->group(function () {
          Route::get('/', [ServiziSvoltiController::class, 'index'])->name('index');
          Route::get('/datatable', [ServiziSvoltiController::class, 'getData'])->name('datatable');
          Route::get('/create', [ServiziSvoltiController::class, 'create'])->name('create');
          Route::post('/', [ServiziSvoltiController::class, 'store'])->name('store');

          Route::get('/{id}', [ServiziSvoltiController::class, 'show'])
               ->whereNumber('id')->name('show');

          Route::get('/{id}/edit', [ServiziSvoltiController::class, 'edit'])
               ->whereNumber('id')->name('edit');

          Route::put('/{id}', [ServiziSvoltiController::class, 'update'])
               ->whereNumber('id')->name('update');

          Route::delete('/{id}', [ServiziSvoltiController::class, 'destroy'])
               ->whereNumber('id')->name('destroy');
     });

     //Ripartizioni per la scelta automezzi
     Route::get('/get-automezzi/{idAssociazione}', [AutomezziController::class, 'getByAssociazione'])
          ->name('get.automezzi');

     Route::post('/automezzi/set-associazione', [AutomezziController::class, 'setAssociazioneSelezionata'])
          ->name('automezzi.setAssociazione');
     // 📦 CRUD automezzi
     Route::resource('automezzi', AutomezziController::class);
});

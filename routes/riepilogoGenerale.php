<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RiepilogoController;

Route::middleware(['auth'])->group(function () {
    // DataTable JSON (deve venire prima delle rotte con parametro per evitare conflitti)
    Route::get('/riepiloghi/data', [RiepilogoController::class, 'getData'])
         ->name('riepiloghi.data');

    // Elenco (index) e creazione (create/store)
    Route::get('/riepiloghi', [RiepilogoController::class, 'index'])
         ->name('riepiloghi.index');
    Route::get('/riepiloghi/create', [RiepilogoController::class, 'create'])
         ->name('riepiloghi.create');
    Route::post('/riepiloghi', [RiepilogoController::class, 'store'])
         ->name('riepiloghi.store');

    // Visualizza, modifica e elimina singolo riepilogo con Route Model Binding
    Route::get('/riepiloghi/{riepilogo}', [RiepilogoController::class, 'show'])
         ->whereNumber('riepilogo')
         ->name('riepiloghi.show');

    Route::get('/riepiloghi/{riepilogo}/edit', [RiepilogoController::class, 'edit'])
         ->whereNumber('riepilogo')
         ->name('riepiloghi.edit');

    Route::put('/riepiloghi/{riepilogo}', [RiepilogoController::class, 'update'])
         ->whereNumber('riepilogo')
         ->name('riepiloghi.update');

    Route::delete('/riepiloghi/{riepilogo}', [RiepilogoController::class, 'destroy'])
         ->whereNumber('riepilogo')
         ->name('riepiloghi.destroy');
});

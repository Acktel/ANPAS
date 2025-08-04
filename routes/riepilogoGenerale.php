<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\RiepilogoController;

Route::middleware(['auth'])->group(function () {

    // ✅ Rotta per cambiare dinamicamente l'anno di riferimento
    Route::post('/cambia-anno', function (Request $request) {
        $request->validate([
            'anno' => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);
        session(['anno_riferimento' => $request->input('anno')]);
        return back(); // o redirect()->route('riepiloghi.index');
    })->name('cambia.anno');

    // 🔁 JSON per DataTables
    Route::get('/riepiloghi/data', [RiepilogoController::class, 'getData'])
        ->name('riepiloghi.data');

    // ➕ Index + Create
    Route::get('/riepiloghi', [RiepilogoController::class, 'index'])
        ->name('riepiloghi.index');
    Route::get('/riepiloghi/create', [RiepilogoController::class, 'create'])
        ->name('riepiloghi.create');
    Route::post('/riepiloghi', [RiepilogoController::class, 'store'])
        ->name('riepiloghi.store');

    // 👁️‍🗨️ Show/Edit/Update/Delete con binding
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

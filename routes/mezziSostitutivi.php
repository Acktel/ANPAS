<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MezziSostitutiviController;
// routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::get('/ajax/rot-sost/stato', [MezziSostitutiviController::class, 'stato']);
    Route::post('/mezzi-sostitutivi/salva', [MezziSostitutiviController::class, 'salva'])
        ->name('mezzi_sostitutivi.salva');

    // pagina edit
    Route::get('/mezzi-sostitutivi/{idConvenzione}/edit', [MezziSostitutiviController::class, 'edit'])
        ->whereNumber('idConvenzione')
        ->name('mezzi_sostitutivi.edit');
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutomezziController;

//Sintassi per prendere tutti i metodi basi per le rotte (create, store, edit,update,destroy)
/**
 * GET /automezzi → index
 * GET /automezzi/create → create
 * POST /automezzi → store
 * GET /automezzi/{id} → show
 * GET /automezzi/{id}/edit → edit
 * PUT /automezzi/{id} → update
 * DELETE /automezzi/{id} → destroy
 */
Route::middleware(['auth'])->group(function () {
    Route::resource('automezzi', AutomezziController::class);
});

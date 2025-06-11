<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConvenzioniController;

//Sintassi per prendere tutti i metodi basi per le rotte (create, store, edit,update,destroy)
Route::middleware(['auth'])->group(function () {
    Route::resource('convenzioni', ConvenzioniController::class);
});

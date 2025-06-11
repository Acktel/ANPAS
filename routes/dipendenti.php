<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DipendenteController;

Route::middleware(['auth'])->group(function () {
     // Rotta AJAX per DataTable
    Route::get('dipendenti/data', [DipendenteController::class, 'getData'])
         ->name('dipendenti.data');
    // 1) Prima dichiariamo i two “sottolisti” (autisti e altro)
    Route::get('dipendenti/autisti', [DipendenteController::class, 'autisti'])
         ->name('dipendenti.autisti');
    Route::get('dipendenti/altro',   [DipendenteController::class, 'altro'])
         ->name('dipendenti.altro');

    // 2) Poi registriamo il resource vero e proprio (index, create, store, show, edit, update, destroy)
    Route::resource('dipendenti', DipendenteController::class);
});
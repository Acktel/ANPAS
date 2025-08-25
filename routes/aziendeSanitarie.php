<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AziendeSanitarieController;

Route::middleware(['auth', 'can:manage-all-associations'])
    ->prefix('aziende-sanitarie')
    ->name('aziende-sanitarie.')
    ->group(function () {

    // Index + DataTable
    Route::get('/',            [AziendeSanitarieController::class, 'index'])->name('index');
    Route::get('/data',        [AziendeSanitarieController::class, 'getData'])->name('data');

    // CRUD
    Route::get('/create',      [AziendeSanitarieController::class, 'create'])->name('create');
    Route::post('/',           [AziendeSanitarieController::class, 'store'])->name('store');
    Route::get('/{id}',        [AziendeSanitarieController::class, 'show'])->name('show');
    Route::get('/{id}/edit',   [AziendeSanitarieController::class, 'edit'])->name('edit');
    Route::patch('/{id}',      [AziendeSanitarieController::class, 'update'])->name('update');
    Route::delete('/{id}',     [AziendeSanitarieController::class, 'destroy'])->name('destroy');
});

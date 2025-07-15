<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RipartizionePersonaleController;
use App\Http\Controllers\RipartizioneVolontarioController;
use App\Http\Controllers\RipartizioneServizioCivileController;
use App\Http\Controllers\RipartizioneMaterialeSanitarioController;
use App\Http\Controllers\CostiPersonaleController;
use App\Http\Controllers\RipartizionePersonaleDettaglioController;

Route::middleware(['auth'])->prefix('ripartizioni')->group(function () {

    // ─── PERSONALE DIPENDENTE ────────────────────────────────────────────────
    Route::prefix('personale')->name('ripartizioni.personale.')->group(function () {
        Route::get('/', [RipartizionePersonaleController::class, 'index'])->name('index');
        Route::get('/data', [RipartizionePersonaleController::class, 'getData'])->name('data');
        Route::get('/create', [RipartizionePersonaleController::class, 'create'])->name('create');
        Route::post('/', [RipartizionePersonaleController::class, 'store'])->name('store');
        Route::get('{idDipendente}/edit', [RipartizionePersonaleController::class, 'edit'])->name('edit');
        Route::put('{idDipendente}', [RipartizionePersonaleController::class, 'update'])->name('update');
        Route::delete('{idDipendente}', [RipartizionePersonaleController::class, 'destroy'])->name('destroy');

        // ─── COSTI PERSONALE ──────────────────────────────────────
        Route::prefix('costi')->name('costi.')->group(function () {
            Route::get('/', [CostiPersonaleController::class, 'index'])->name('index');
            Route::get('/data', [CostiPersonaleController::class, 'getData'])->name('data');
            Route::get('/create', [CostiPersonaleController::class, 'create'])->name('create');
            Route::get('/{id}/edit', [CostiPersonaleController::class, 'edit'])->name('edit');
            Route::get('/{id}', [CostiPersonaleController::class, 'show'])->name('show');
            Route::put('/{id}', [CostiPersonaleController::class, 'update'])->name('update');
            Route::delete('/{id}', [CostiPersonaleController::class, 'destroy'])->name('destroy');
        });

        // ─── DETTAGLIO RIPARTIZIONE ──────────────────────────────
        Route::get('/dettaglio', [RipartizionePersonaleDettaglioController::class, 'index'])->name('dettaglio');
        Route::post('/dettaglio/salva', [RipartizionePersonaleDettaglioController::class, 'store'])->name('dettaglio.salva');
    });

    // ─── VOLONTARI ────────────────────────────────────────────────
    Route::prefix('volontari')->name('ripartizioni.volontari.')->group(function () {
        Route::get('/', [RipartizioneVolontarioController::class, 'index'])->name('index');
        Route::get('/data', [RipartizioneVolontarioController::class, 'getData'])->name('data');
        Route::get('/edit', [RipartizioneVolontarioController::class, 'edit'])->name('edit');
        Route::put('/update', [RipartizioneVolontarioController::class, 'update'])->name('update');
    });

    // ─── SERVIZIO CIVILE ─────────────────────────────────────────
    Route::prefix('servizio-civile')->name('ripartizioni.servizio_civile.')->group(function () {
        Route::get('/', [RipartizioneServizioCivileController::class, 'index'])->name('index');
        Route::get('/data', [RipartizioneServizioCivileController::class, 'getData'])->name('data');
        Route::get('/edit', [RipartizioneServizioCivileController::class, 'edit'])->name('edit');
        Route::put('/update', [RipartizioneServizioCivileController::class, 'update'])->name('update');
    });

    // ─── MATERIALE SANITARIO ─────────────────────────────────────
    Route::prefix('materiale-sanitario')->name('ripartizioni.materiale_sanitario.')->group(function () {
        Route::get('/', [RipartizioneMaterialeSanitarioController::class, 'index'])->name('index');
        Route::get('/data', [RipartizioneMaterialeSanitarioController::class, 'getData'])->name('data');
        Route::get('/edit', [RipartizioneMaterialeSanitarioController::class, 'edit'])->name('edit');
        Route::put('/update', [RipartizioneMaterialeSanitarioController::class, 'update'])->name('update');
        Route::post('/aggiorna-inclusione', [RipartizioneMaterialeSanitarioController::class, 'aggiornaInclusione'])->name('aggiornaInclusione');
    });
});

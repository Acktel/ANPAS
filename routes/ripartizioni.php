<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RipartizionePersonaleController;
use App\Http\Controllers\RipartizioneVolontarioController;
use App\Http\Controllers\RipartizioneServizioCivileController;
use App\Http\Controllers\RipartizioneMaterialeSanitarioController;
use App\Http\Controllers\CostiPersonaleController;
use App\Http\Controllers\CostiAutomezziController;
use App\Http\Controllers\CostiRadioController;
use App\Http\Controllers\CostoMaterialeSanitarioController;
use App\Http\Controllers\CostoOssigenoController;
use App\Http\Controllers\RipartizioneCostiAutomezziSanitariController;

Route::middleware(['auth'])->prefix('ripartizioni')->group(function () {

    // ─── PERSONALE DIPENDENTE ────────────────────────────────────────────────
    Route::prefix('personale')->name('ripartizioni.personale.')->group(function () {
        Route::get('/', [RipartizionePersonaleController::class, 'index'])->name('index');
        Route::get('/data', [RipartizionePersonaleController::class, 'getData'])->name('data');
        Route::get('/create', [RipartizionePersonaleController::class, 'create'])->name('create');
        Route::post('/', [RipartizionePersonaleController::class, 'store'])->name('store');
        Route::get('{idDipendente}', [RipartizionePersonaleController::class, 'show'])
            ->where('idDipendente', '[0-9]+')
            ->name('show');
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
            Route::get('/{idDipendente}', [CostiPersonaleController::class, 'show'])->name('ripartizioni.personale.costi.show');
        });
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

    // ─── COSTI AUTOMEZZI ─────────────────────────────────────
    Route::prefix('costi-automezzi')->name('ripartizioni.costi_automezzi.')->group(function () {
        Route::get('/', [CostiAutomezziController::class, 'index'])->name('index');
        Route::get('/data', [CostiAutomezziController::class, 'getData'])->name('data');
        Route::get('/{idAutomezzo}/edit', [CostiAutomezziController::class, 'edit'])->name('edit');
        Route::put('/{idAutomezzo}', [CostiAutomezziController::class, 'update'])->name('update');
    });

    // ─── COSTI RADIO ─────────────────────────────────────────────
    Route::prefix('costi-radio')->name('ripartizioni.costi_radio.')->group(function () {
        Route::get('/', [CostiRadioController::class, 'index'])->name('index');
        Route::get('/data', [CostiRadioController::class, 'getData'])->name('getData');
        Route::get('/edit-totale', [CostiRadioController::class, 'editTotale'])->name('editTotale');
        Route::put('/update-totale', [CostiRadioController::class, 'updateTotale'])->name('updateTotale');
    });

    // ─── COSTI MATERIALE SANITARIO ─────────────────────────────────────────────
    Route::prefix('imputazioni/materiale-sanitario')
        ->name('imputazioni.materiale_sanitario.')
        ->middleware('auth')
        ->group(function () {
            Route::get('/', [CostoMaterialeSanitarioController::class, 'index'])->name('index');
            Route::get('/edit-totale', [CostoMaterialeSanitarioController::class, 'editTotale'])->name('editTotale');
            Route::post('/update-totale', [CostoMaterialeSanitarioController::class, 'updateTotale'])->name('updateTotale');
            Route::get('/get-data', [CostoMaterialeSanitarioController::class, 'getData'])->name('getData');
        });

    // ─── COSTI OSSIGENO ───────────────────────────────────────────────────────────
    Route::prefix('imputazioni/ossigeno')
        ->name('imputazioni.ossigeno.')
        ->middleware('auth')
        ->group(function () {
            Route::get('/', [CostoOssigenoController::class, 'index'])->name('index');
            Route::get('/edit-totale', [CostoOssigenoController::class, 'editTotale'])->name('editTotale');
            Route::post('/update-totale', [CostoOssigenoController::class, 'updateTotale'])->name('updateTotale');
            Route::get('/get-data', [CostoOssigenoController::class, 'getData'])->name('getData');
        });

    // ─── COSTI AUTOMEZZI RADIO E SANITARI ─────────────────────────────────────────────
    Route::prefix('costi-automezzi-sanitari')->name('ripartizioni.costi_automezzi_sanitari.')->group(function () {
        Route::get('/', [RipartizioneCostiAutomezziSanitariController::class, 'index'])->name('index');
        Route::get('/data', [RipartizioneCostiAutomezziSanitariController::class, 'getData'])->name('getData');

        // ✅ Nuova rotta per la tabella finale (ripartizione completa per voce/convenzione)
        Route::get('/tabella-finale', [RipartizioneCostiAutomezziSanitariController::class, 'getTabellaFinale'])->name('tabellaFinale');
    });

});

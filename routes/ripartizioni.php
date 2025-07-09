<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RipartizionePersonaleController;
use App\Http\Controllers\RipartizioneVolontarioController;
use App\Http\Controllers\RipartizioneServizioCivileController;
use App\Http\Controllers\RipartizioneMaterialeSanitarioController;

// (in futuro, qui potrai aggiungere altri controller di ripartizione)

Route::middleware(['auth'])
    ->prefix('ripartizioni')
    ->group(function () {

        // Index
        //
        // ─── PERSONALE DIPENDENTE ────────────────────────────────────────────────
        //
        Route::prefix('personale')
            ->name('ripartizioni.personale.')
            ->group(function () {
                Route::get('/',    [RipartizionePersonaleController::class, 'index'])
                    ->name('index');
                Route::get('data', [RipartizionePersonaleController::class, 'getData'])
                    ->name('data');
                // create/edit/store/update/destroy/show se serve…
                // Create / Store
                Route::get('create', [RipartizionePersonaleController::class, 'create'])
                    ->name('create');
                Route::post('/', [RipartizionePersonaleController::class, 'store'])
                    ->name('store');

                // Edit / Update
                Route::get('{idDipendente}/edit', [RipartizionePersonaleController::class, 'edit'])
                    ->name('edit');
                Route::put('{idDipendente}', [RipartizionePersonaleController::class, 'update'])
                    ->name('update');

                // Destroy
                Route::delete('{idDipendente}', [RipartizionePersonaleController::class, 'destroy'])
                    ->name('destroy');
            });

        Route::prefix('ripartizioni/volontari')->middleware('auth')->group(function () {
            Route::get('/',     [RipartizioneVolontarioController::class, 'index'])
                ->name('ripartizioni.volontari.index');
            Route::get('/data', [RipartizioneVolontarioController::class, 'getData'])
                ->name('ripartizioni.volontari.data');
            Route::get('/edit', [RipartizioneVolontarioController::class, 'edit'])
                ->name('ripartizioni.volontari.edit');
            Route::put('/update', [RipartizioneVolontarioController::class, 'update'])
                ->name('ripartizioni.volontari.update');
        });

        Route::prefix('ripartizioni/servizio-civile')->middleware('auth')->group(function () {
            Route::get('/',        [RipartizioneServizioCivileController::class, 'index'])
                ->name('ripartizioni.servizio_civile.index');
            Route::get('/data',    [RipartizioneServizioCivileController::class, 'getData'])
                ->name('ripartizioni.servizio_civile.data');
            Route::get('/edit',    [RipartizioneServizioCivileController::class, 'edit'])
                ->name('ripartizioni.servizio_civile.edit');
            Route::put('/update',  [RipartizioneServizioCivileController::class, 'update'])
                ->name('ripartizioni.servizio_civile.update');
        });

        // ─── MATERIALE SANITARIO ─────────────────────────────
        Route::prefix('materiale-sanitario')->name('ripartizioni.materiale_sanitario.')->group(function () {
            Route::get('/', [RipartizioneMaterialeSanitarioController::class, 'index'])->name('index');
            Route::get('/data', [RipartizioneMaterialeSanitarioController::class, 'getData'])->name('data');
            Route::get('/edit', [RipartizioneMaterialeSanitarioController::class, 'edit'])->name('edit');
            Route::put('/update', [RipartizioneMaterialeSanitarioController::class, 'update'])->name('update');
            Route::post('/aggiorna-inclusione', [RipartizioneMaterialeSanitarioController::class, 'aggiornaInclusione'])->name('aggiornaInclusione');
        });
    });

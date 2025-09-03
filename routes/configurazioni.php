<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConfigurazioneVeicoliController;
use App\Http\Controllers\ConfigurazionePersonaleController;
use App\Http\Controllers\ConfigurazioneLottiController;
use App\Http\Controllers\ConfigurazioneRiepilogoController;

Route::middleware(['auth'])->prefix('configurazioni')->group(function () {
    // VEICOLI
    Route::get('veicoli', [ConfigurazioneVeicoliController::class, 'index'])->name('configurazioni.veicoli');
    Route::post('tipologia-veicolo', [ConfigurazioneVeicoliController::class, 'storeVehicleType'])->name('configurazioni.tipologia-veicolo.store');
    Route::delete('tipologia-veicolo/{id}', [ConfigurazioneVeicoliController::class, 'destroyVehicleType'])->name('configurazioni.tipologia-veicolo.destroy');
    Route::post('carburante', [ConfigurazioneVeicoliController::class, 'storeFuelType'])->name('configurazioni.carburante.store');
    Route::delete('carburante/{id}', [ConfigurazioneVeicoliController::class, 'destroyFuelType'])->name('configurazioni.carburante.destroy');

    // PERSONALE
    Route::get('personale', [ConfigurazionePersonaleController::class, 'index'])->name('configurazioni.personale');
    Route::post('qualifiche', [ConfigurazionePersonaleController::class, 'storeQualifica'])->name('configurazioni.qualifiche.store');
    Route::delete('qualifiche/{id}', [ConfigurazionePersonaleController::class, 'destroyQualifica'])->name('configurazioni.qualifiche.destroy');
    Route::post('contratti', [ConfigurazionePersonaleController::class, 'storeContratto'])->name('configurazioni.contratti.store');
    Route::delete('contratti/{id}', [ConfigurazionePersonaleController::class, 'destroyContratto'])->name('configurazioni.contratti.destroy');

    // LIVELLI MANSIONE
    Route::post('livelli', [ConfigurazionePersonaleController::class, 'storeLivelloMansione'])->name('configurazioni.livelli.store');
    Route::delete('livelli/{id}', [ConfigurazionePersonaleController::class, 'destroyLivelloMansione'])->name('configurazioni.livelli.destroy');

    //AZIENDE SANITARIE
    Route::get('aziende-sanitarie', [ConfigurazioneLottiController::class, 'index'])->name('configurazioni.aziende_sanitarie');
    Route::post('aziende-sanitarie', [ConfigurazioneLottiController::class, 'store'])->name('configurazioni.aziende_sanitarie.store');
    Route::delete('aziende-sanitarie/{id}', [ConfigurazioneLottiController::class, 'destroy'])->name('configurazioni.aziende_sanitarie.destroy');

    // RIEPILOGO COSTI
    Route::get('riepilogo', [ConfigurazioneRiepilogoController::class, 'index'])->name('configurazioni.riepilogo.index');
    Route::post('riepilogo', [ConfigurazioneRiepilogoController::class, 'store'])->name('configurazioni.riepilogo.store');
    Route::put('riepilogo/{id}', [ConfigurazioneRiepilogoController::class, 'update'])->name('configurazioni.riepilogo.update');
    Route::delete('riepilogo/{id}', [ConfigurazioneRiepilogoController::class, 'destroy'])->name('configurazioni.riepilogo.destroy');
    Route::post('riepilogo/reorder', [ConfigurazioneRiepilogoController::class, 'reorder'])->name('configurazioni.riepilogo.reorder');
<<<<<<< HEAD

    //CONVENZIONI
    // Route::get('personale', [ConfigurazionePersonaleController::class, 'index'])->name('configurazioni.convenzioni');
    // Route::post('qualifiche', [ConfigurazionePersonaleController::class, 'storeQualifica'])->name('configurazioni.convenzioni.store');
    // Route::delete('qualifiche/{id}', [ConfigurazionePersonaleController::class, 'destroyQualifica'])->name('configurazioni.convenzioni.destroy');
=======
>>>>>>> refs/remotes/origin/main
});

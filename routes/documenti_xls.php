<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentiExcelController;

Route::middleware(['auth'])->group(function () {

    // Form & archivio per export Excel (Registro)
    Route::get('documenti/registro-xls', [DocumentiExcelController::class, 'registroXls'])
        ->name('documenti.registro_xls');

    // Avvio job REGISTRI – Prima pagina (XLS)
    Route::post('documenti/registri/pagina1/xls', [DocumentiExcelController::class, 'startRegistriP1Xls'])
        ->name('documenti.registri_p1.xls');
    
    Route::post('/documenti/schede-riparto-costi/xls', [DocumentiExcelController::class, 'startSchedeRipartoCostiXls'])
    ->name('documenti.schede_riparto_costi.xls');

    /*
    =====================================================
    Qui in futuro aggiungi gli altri endpoint Excel.
    Es:
    Route::post('documenti/registri/full/xls', [DocumentiExcelController::class, 'startRegistriFullXls'])
        ->name('documenti.registri_full.xls');

    Route::post('documenti/distinta-imputazione/xls', [DocumentiExcelController::class, 'startDistintaImputazioneXls'])
        ->name('documenti.distinta_imputazione.xls');
    =====================================================
    */

    // NB: per status e download continuiamo ad usare le stesse rotte già in documenti.php
    // Route::get('documenti/status/{id}', [DocumentiController::class, 'status'])->name('documenti.status');
    // Route::get('documenti/registro/download/{id}', [DocumentiController::class, 'download'])->name('documenti.download');
});

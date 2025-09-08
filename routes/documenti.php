<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentiController;

Route::middleware(['auth'])->group(function () {
     // Form & generazione Excel per il Registro
     Route::get('documenti/registro', [DocumentiController::class, 'registroForm'])
          ->name('documenti.registro');
     Route::post('documenti/registro', [DocumentiController::class, 'registroGenerate'])
          ->name('documenti.registro.generate');

     // Stessa cosa per Distinta Imputazione
     Route::get('documenti/distinta', [DocumentiController::class, 'distintaForm'])
          ->name('documenti.distinta');
     Route::post('documenti/distinta', [DocumentiController::class, 'distintaGenerate'])
          ->name('documenti.distinta.generate');

     // E per Criteri Imputazione
     Route::get('documenti/criteri', [DocumentiController::class, 'criteriForm'])
          ->name('documenti.criteri');
     Route::post('documenti/criteri', [DocumentiController::class, 'criteriGenerate'])
          ->name('documenti.criteri.generate');

     Route::get('/documenti/registro/download/{id}', [DocumentiController::class, 'download'])->name('documenti.download');

     // avvia generazione PDF riepilogo costi (POST esistente)
     Route::post('/documenti/riepilogo-costi/pdf', [DocumentiController::class, 'riepilogoCostiPdf'])
          ->name('documenti.riepilogo_costi.pdf');

     Route::post('/documenti/registro-automezzi/pdf', [DocumentiController::class, 'registroAutomezziPdf'])
          ->name('documenti.registro_automezzi.pdf');

     Route::post(
          '/documenti/distinta-km-percorsi/pdf',
          [DocumentiController::class, 'distintaKmPercorsiPdf']
     )->name('documenti.distinta_km_percorsi.pdf');

     Route::post(
          '/documenti/km-percentuali/pdf', [DocumentiController::class, 'kmPercentualiPdf']
     )->name('documenti.km_percentuali.pdf');

     Route::post('/documenti/servizi-svolti/pdf', [DocumentiController::class, 'serviziSvoltiPdf'])
     ->name('documenti.servizi_svolti.pdf');



     // stato documento per polling
     Route::get('/documenti/status/{id}', [DocumentiController::class, 'status'])
          ->name('documenti.status');
});

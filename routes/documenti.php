<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentiController;
use App\Http\Controllers\RiepilogoPdfController;


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
          '/documenti/km-percentuali/pdf',
          [DocumentiController::class, 'kmPercentualiPdf']
     )->name('documenti.km_percentuali.pdf');

     Route::post('/documenti/servizi-svolti/pdf', [DocumentiController::class, 'serviziSvoltiPdf'])
          ->name('documenti.servizi_svolti.pdf');


     Route::post('/documenti/rapporti-ricavi/pdf', [DocumentiController::class, 'rapportiRicaviPdf'])
          ->name('documenti.rapporti_ricavi.pdf');

     Route::post(
          '/documenti/ripartizione-personale/pdf',
          [DocumentiController::class, 'ripartizionePersonalePdf']
     )->name('documenti.ripartizione_personale.pdf');

     Route::post(
          '/documenti/ripartizione-volontari-scn/pdf',
          [DocumentiController::class, 'ripVolontariScnPdf']
     )->name('documenti.rip_volontari_scn.pdf');

     Route::post(
          '/documenti/servizi-svolti-ossigeno/pdf',
          [DocumentiController::class, 'serviziSvoltiOssigenoPdf']
     )->name('documenti.servizi_svolti_ossigeno.pdf');

     Route::post(
          '/documenti/costi-automezzi-sanitari.pdf',
          [DocumentiController::class, 'costiAutomezziSanitariPdf']
     )->name('documenti.costi_automezzi_sanitari.pdf');

     Route::post(
          '/documenti/costi-personale/pdf',
          [DocumentiController::class, 'costiPersonalePdf']
     )->name('documenti.costi_personale.pdf');

     Route::post(
          '/documenti/costi-radio/pdf',
          [DocumentiController::class, 'costiRadioPdf']
     )->name('documenti.costi_radio.pdf');

     Route::post(
          '/documenti/imputazioni-materiale-ossigeno/pdf',
          [DocumentiController::class, 'imputazioniMaterialeOssigenoPdf']
     )->name('documenti.imputazioni_materiale_ossigeno.pdf');

     Route::post(
          '/documenti/ripartizione-costi-automezzi-riepilogo/pdf',
          [DocumentiController::class, 'ripartizioneCostiAutomezziRiepilogoPdf']
     )->name('documenti.ripartizione_costi_automezzi_riepilogo.pdf');

     // routes/web.php
     Route::post(
          '/documenti/distinta-imputazione-costi/pdf',
          [DocumentiController::class, 'distintaImputazioneCostiPdf']
     )->name('documenti.distinta_imputazione_costi.pdf');

     Route::post(
          '/documenti/riepiloghi-dati-costi/pdf',
          [DocumentiController::class, 'riepiloghiDatiECostiPdf']
     )->name('documenti.riepiloghi_dati_costi.pdf');

     Route::post(
          '/documenti/documento-unico/pdf',
          [DocumentiController::class, 'documentoUnicoPdf']
     )->name('documenti.documento_unico.pdf');

     Route::post(
          '/documenti/bundle-all/pdf',
          [DocumentiController::class, 'bundleAllPdf']
     )->name('documenti.bundle_all.pdf');

     // stato documento per polling
     Route::get('/documenti/status/{id}', [DocumentiController::class, 'status'])
          ->name('documenti.status');
});

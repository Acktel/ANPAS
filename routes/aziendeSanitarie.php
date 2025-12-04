<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\AziendeSanitarieController;

Route::prefix('aziende-sanitarie')
    ->name('aziende-sanitarie.')
    ->group(function () {

        // =======================
        // AJAX SESSION HELPERS
        // =======================
        Route::post('/sessione/setAssociazione', function (Request $request) {
            session(['associazione_selezionata' => (int)$request->input('idAssociazione')]);
            return response()->json(['ok' => true]);
        })->name('sessione.setAssociazione');

        Route::post('/sessione/setConvenzione', function (Request $request) {
            session(['convenzione_selezionata' => (int)$request->input('idConvenzione')]);
            return response()->json(['ok' => true]);
        })->name('sessione.setConvenzione');

        Route::get('/ajax/convenzioni-by-associazione/{id}', function ($id) {
            $anno = session('anno_riferimento', now()->year);

            return DB::table('convenzioni')
                ->select('idConvenzione as id', 'Convenzione as text')
                ->where('idAssociazione', $id)
                ->where('idAnno', $anno)
                ->orderBy('ordinamento')
                ->orderBy('idConvenzione')
                ->get();
        })->name('ajax.convenzioniByAssociazione');


        // =======================
        // Index + DataTable
        // =======================
        Route::get('/',       [AziendeSanitarieController::class, 'index'])->name('index');
        Route::get('/data',   [AziendeSanitarieController::class, 'getData'])->name('data');

        // =======================
        // CRUD
        // =======================
        Route::get('/create',        [AziendeSanitarieController::class, 'create'])->name('create');
        Route::post('/',             [AziendeSanitarieController::class, 'store'])->name('store');

        Route::get('/{id}', [AziendeSanitarieController::class, 'show'])->name('show');
        
        Route::get('/{id}/edit',     [AziendeSanitarieController::class, 'edit'])->name('edit');
        Route::patch('/{id}',        [AziendeSanitarieController::class, 'update'])->name('update');
        Route::delete('/{id}',       [AziendeSanitarieController::class, 'destroy'])->name('destroy');


        // =======================
        // DUPLICAZIONE ANNO
        // =======================
        Route::get('/check-duplicazione', [AziendeSanitarieController::class, 'checkDuplicazioneDisponibile'])
            ->name('checkDuplicazione');

        Route::post('/duplica', [AziendeSanitarieController::class, 'duplicaAnnoPrecedente'])
            ->name('duplica');
});

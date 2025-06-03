<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1) Rotte di autenticazione Laravel
Auth::routes();

// 2) Redirect “/” e “/home” verso “/dashboard”
Route::redirect('/', '/dashboard');
Route::redirect('/home', '/dashboard');

// 3) Dashboard principale
Route::get('/dashboard', [HomeController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

// 4) Gruppo di rotte riservate agli utenti autenticati
Route::middleware('auth')->group(function () {

    // 4.1) Profilo utente
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/',    [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/',  [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // 4.2) Impersonazione (solo chi ha il permesso “impersonate-users”)
    Route::post('/impersonate/{userId}', [ImpersonationController::class, 'start'])
         ->name('impersonate.start')
         ->middleware('can:impersonate-users');

    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
         ->name('impersonate.stop');

    // 4.3) Sezione Automezzi (rotte incluse da file esterno)
    require __DIR__ . '/automezzi.php';

    // 4.4) Sezione Associazioni e UTENTI (rotte incluse da file esterno)
    require __DIR__ . '/associazioni.php';
});

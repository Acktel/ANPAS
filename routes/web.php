<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AutomezzoController;
use App\Http\Controllers\Auth\AssociationRegisterController;
use App\Http\Controllers\Auth\AssociationLoginController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Admin\RoleController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::redirect('/', '/home');

Route::get('/home', [HomeController::class, 'index'])
    ->middleware('auth')
    ->name('home');

// Tutte le rotte che richiedono autenticazione
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::view('/dashboard', 'dashboard')
        ->middleware('verified')
        ->name('dashboard');

    // Profilo utente
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/',    [ProfileController::class, 'edit'])->name('edit');
        Route::patch('/',  [ProfileController::class, 'update'])->name('update');
        Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
    });

    // CRUD automezzi
    Route::resource('automezzi', AutomezzoController::class);

    // Sezione Admin: solo “admin”
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {

            // Gestione ruoli
            Route::resource('roles', RoleController::class)
                ->parameters(['roles' => 'role']);

            // Gestione ruoli utenti
            Route::get('users/{user}/roles',  [UserRoleController::class, 'edit'])->name('users.roles.edit');
            Route::put('users/{user}/roles',  [UserRoleController::class, 'update'])->name('users.roles.update');
            Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        
        });
});

// Autenticazione/registrazione Associazioni (guest-only)
Route::prefix('assoc')
    ->name('assoc.')
    ->middleware('guest')
    ->group(function () {
        Route::get('register', [AssociationRegisterController::class, 'showRegistrationForm'])->name('register');
        Route::post('register', [AssociationRegisterController::class, 'register']);

        Route::get('login',    [AssociationLoginController::class, 'showLoginForm'])->name('login');
        Route::post('login',   [AssociationLoginController::class, 'login']);
    });

// Logout associazioni (richiede auth)
Route::post('assoc/logout', [AssociationLoginController::class, 'logout'])
    ->middleware('auth')
    ->name('assoc.logout');

require __DIR__ . '/auth.php';

<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssociazioniController;
use App\Http\Controllers\AssociationUsersController;
use App\Http\Controllers\AdminAllUsersController;

/*
|--------------------------------------------------------------------------
| Rotte “Associazioni” e “Utenti” (incluse da web.php)
|--------------------------------------------------------------------------
|
| Qui raggruppiamo:
|   – /associazioni/*              (Admin e Supervisor)
|   – /all-users/*                 (Admin e Supervisor)
|   – /my-users/*                  (solo AdminUser)
|
*/
// Rotte “Associazioni” e “Utenti”
Route::middleware('can:manage-all-associations')->group(function() {
    Route::get('/associazioni',            [AssociazioniController::class, 'index'])->name('associazioni.index');
    Route::get('/associazioni/data',       [AssociazioniController::class, 'getData'])->name('associazioni.data');
    Route::get('/associazioni/create',     [AssociazioniController::class, 'create'])->name('associazioni.create');
    Route::post('/associazioni',           [AssociazioniController::class, 'store'])->name('associazioni.store');
    Route::get('/associazioni/{id}/edit',  [AssociazioniController::class, 'edit'])->name('associazioni.edit');
    Route::patch('/associazioni/{id}',     [AssociazioniController::class, 'update'])->name('associazioni.update');
    Route::delete('/associazioni/{id}',    [AssociazioniController::class, 'destroy'])->name('associazioni.destroy');

    // Lista di TUTTI gli utenti di tutte le associazioni (Admin e Supervisor)
    Route::get('/all-users',      [AdminAllUsersController::class, 'index'])->name('all-users.index');
    Route::get('/all-users/data', [AdminAllUsersController::class, 'getData'])->name('all-users.data');
    Route::get('/all-users/create', [AdminAllUsersController::class, 'create'])->name('all-users.create');
    Route::post('/all-users', [AdminAllUsersController::class, 'store'])->name('all-users.store');
    Route::get('/all-users/{id}/edit', [AdminAllUsersController::class, 'edit'])->name('all-users.edit');
    Route::put('/all-users/{id}',      [AdminAllUsersController::class, 'update'])->name('all-users.update');
    Route::delete('/all-users/{id}', [AdminAllUsersController::class, 'destroy'])->name('all-users.destroy');

});


// 2) Rotte per chi ha gate “manage-own-association” (solo AdminUser)
Route::middleware('can:manage-own-association')->group(function() {
    Route::get('/my-users',            [AssociationUsersController::class, 'index'])->name('my-users.index');
    Route::get('/my-users/data',       [AssociationUsersController::class, 'getData'])->name('my-users.data');
    Route::get('/my-users/create',     [AssociationUsersController::class, 'create'])->name('my-users.create');
    Route::post('/my-users',           [AssociationUsersController::class, 'store'])->name('my-users.store');
    Route::get('/my-users/{id}/edit',  [AssociationUsersController::class, 'edit'])->name('my-users.edit');     // ✅ AGGIUNTA
    Route::put('/my-users/{id}',       [AssociationUsersController::class, 'update'])->name('my-users.update'); // ✅ AGGIUNTA
    Route::delete('/my-users/{id}',    [AssociationUsersController::class, 'destroy'])->name('my-users.destroy');
});
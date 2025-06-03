<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // …
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Solo SuperAdmin e Admin possono impersonare
        Gate::define('impersonate-users', function ($user) {
            return $user->hasAnyRole('SuperAdmin', 'Admin');
        });

        // Solo AdminUser può gestire gli utenti della propria associazione
        Gate::define('manage-own-association', function ($user) {
            return $user->hasRole('AdminUser');
        });

        // Admin, Supervisor e SuperAdmin possono gestire TUTTE le associazioni
        Gate::define('manage-all-associations', function ($user) {
            // ==== DEBUG LOG e dd() qui dentro ====
            Log::info("Invocato gate manage-all-associations per utente #{$user->id}: ruoli = " . $user->getRoleNames()->implode(','));
            $allowed = $user->hasAnyRole(['SuperAdmin','Admin','Supervisor']);
            Log::info("Risultato controllo: " . ($allowed ? 'true' : 'false'));
            return $allowed;

        });
    }
}

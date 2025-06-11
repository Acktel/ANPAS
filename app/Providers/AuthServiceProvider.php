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

        // *** Se sto impersonando, bypasso tutti i controlli di autorizzazione ***
        Gate::before(function ($user, $ability) {
            if (session()->has('impersonate')) {
                Log::info("DEBUG Gate::before: sto impersonando, bypasso il controllo per ability '{$ability}'.");
                return true;
            }
            return null;
        });

        // Solo SuperAdmin , Admin e Supervisor possono impersonare
        Gate::define('impersonate-users', function ($user) {
            return $user->hasAnyRole('SuperAdmin', 'Admin', 'Supervisor');
        });

        // Solo AdminUser può gestire gli utenti della propria associazione
        Gate::define('manage-own-association', function ($user) {
            return $user->hasRole('SuperAdmin','AdminUser');
        });

        // Admin, Supervisor e SuperAdmin possono gestire TUTTE le associazioni
        Gate::define('manage-all-associations', function ($user) {
            Log::info("Invocato gate manage-all-associations per utente #{$user->id}: ruoli = " . $user->getRoleNames()->implode(','));
            $allowed = $user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor']);
            Log::info("Risultato controllo: " . ($allowed ? 'true' : 'false'));
            return $allowed;
        });

        // Solo chi sta attualmente impersonando può stoppare l’impersonazione
        Gate::define('stop-impersonation', function ($user) {
            Log::info("DEBUG: StopImpersonation gate invocato. Session impersonate? " . (session()->has('impersonate') ? 'yes' : 'no'));
            return session()->has('impersonate');
        });
    }
}

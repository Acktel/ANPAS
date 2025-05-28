<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

// Importa i tuoi Modelli…
use App\Models\Associazione;
use App\Models\Convenzione;
use App\Models\Costo;

// …e le corrispondenti Policy
use App\Policies\AssociazionePolicy;
use App\Policies\ConvenzionePolicy;
use App\Policies\CostoPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mappa Modelli → Policy
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Associazione::class  => AssociazionePolicy::class,
        Convenzione::class   => ConvenzionePolicy::class,
        Costo::class         => CostoPolicy::class,
    ];

    /**
     * Registra le Policy e qualsiasi Gate aggiuntivo.
     */
    public function boot()
    {
        $this->registerPolicies();

        // Se ti servono Gate custom, puoi definirli qui:
        // Gate::define('is-admin', fn($user) => $user->hasRole('Admin'));
    }
}

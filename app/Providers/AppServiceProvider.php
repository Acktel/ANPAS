<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
        // Imposta anno di default se non presente
        if (!Session::has('anno_riferimento')) {
            Session::put('anno_riferimento', now()->year);
        }

        // Condividi con tutte le viste
        View::share('anno_riferimento', Session::get('anno_riferimento'));

        RateLimiter::for('pdf-riepilogo', function () {
            // esempio: max 4 generazioni/minuto a livello globale
            return [Limit::perMinute(5)];
        });
    }
}

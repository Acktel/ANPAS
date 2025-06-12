<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AnnoRiferimentoMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Se la sessione non ha l’anno di riferimento, lo imposta
        if (!session()->has('anno_riferimento')) {
            session(['anno_riferimento' => now()->year]);
        }

        return $next($request);
    }
}

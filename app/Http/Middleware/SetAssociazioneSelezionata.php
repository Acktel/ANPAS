<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SetAssociazioneSelezionata {
    public function handle(Request $request, Closure $next) {
        $user = Auth::user();

        if (!$user) return $next($request);

        if ($user->hasAnyRole(['SuperAdmin', 'Admin', 'Supervisor'])) {
            $selected = $request->query('idAssociazione');

            if ($selected) {
                session(['idAssociazione' => (int) $selected]);
            }
        } else {
            // Per ruoli normali, forziamo sempre l’associazione dell’utente
            session(['idAssociazione' => $user->IdAssociazione]);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Avvia l’impersonazione: salva l’ID originale e fai login come $userId.
     */
    public function start($userId)
    {
        $admin = Auth::user();

        // Se stai già impersonando, blocca
        if (session()->has('impersonate')) {
            return redirect()->back()->with('error', 'Sei già in modalità impersonazione.');
        }

        // Salva l’ID dell’admin originale nella chiave "impersonate"
        session(['impersonate' => $admin->id]);

        // Fai login come l’utente target
        Auth::loginUsingId($userId);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Ora stai impersonando l’utente #'.$userId);
    }

    /**
     * Termina l’impersonazione: ripristina l’utente originale.
     */
    public function stop()
    {
        // Se non c’è la chiave "impersonate" in sessione, non sei in impersonazione
        if (! session()->has('impersonate')) {
            return redirect()->back()->with('error', 'Non sei in modalità impersonazione.');
        }

        // Recupera l’ID originale e rimuovi la chiave dalla sessione
        $originalId = session('impersonate');
        session()->forget('impersonate');

        // Fai login come l’utente originale
        Auth::loginUsingId($originalId);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Hai terminato l’impersonazione.');
    }
}

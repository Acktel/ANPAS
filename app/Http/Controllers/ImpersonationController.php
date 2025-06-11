<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class ImpersonationController extends Controller
{
    /**
     * Avvia l’impersonazione: salva l’ID originale e fai login come $userId.
     */
    public function start($userId)
    {
        $admin = Auth::user();

        if (Session::has('impersonate')) {
            return redirect()->back()->with('error', 'Sei già in modalità impersonazione.');
        }

        // 1) Salva in sessione l’ID dell’admin originale
        Session::put('impersonate', $admin->id);
        Log::info("DEBUG start(): salvato in sessione impersonate = {$admin->id}");

        // 2) Esegui login come utente target
        Auth::loginUsingId($userId);
        Log::info("DEBUG start(): login come utente #{$userId}. Auth::id() ora = " . Auth::id());

        // 3) Redirect alla dashboard come utente impersonato
        return redirect()
            ->route('dashboard')
            ->with('success', 'Ora stai impersonando l’utente #' . $userId);
    }

    /**
     * Termina l’impersonazione: ripristina l’utente originale.
     */
    public function stop()
    {
        Log::info("DEBUG stop(): chiamato. Auth::id() attuale = " . Auth::id());
        Log::info("DEBUG stop(): session('impersonate') = " . (Session::has('impersonate') ? Session::get('impersonate') : 'NO-KEY'));

        // 1) Verifica che la chiave "impersonate" esista in sessione
        if (! Session::has('impersonate')) {
            Log::warning("DEBUG stop(): nessuna chiave 'impersonate' in sessione, torno indietro.");
            return redirect()->back()->with('error', 'Non sei in modalità impersonazione.');
        }

        // 2) Preleva e rimuove la chiave "impersonate"
        $originalId = Session::pull('impersonate');
        Log::info("DEBUG stop(): recuperato originalId = {$originalId} e tolta la chiave sessione.");

        // 3) Esegui login come utente originale
        Auth::loginUsingId($originalId);
        Log::info("DEBUG stop(): eseguito login come utente originale #{$originalId}. Auth::id() ora = " . Auth::id());

        // 4) Rigenera la sessione per pulire eventuali dati residui
        Session::regenerate();
        Log::info("DEBUG stop(): sessione rigenerata.");

        // 5) Redirect definitivo alla dashboard del SuperAdmin
        return redirect()
            ->route('dashboard')
            ->with('success', 'Hai terminato l’impersonazione e sei tornato come SuperAdmin.');
    }
}

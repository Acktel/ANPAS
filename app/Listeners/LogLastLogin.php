<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class LogLastLogin
{
    public function handle(Login $event)
    {
        Log::info('Listener LogLastLogin attivato per user ID: ' . $event->user->id);
        $event->user->update([
            'last_login_at' => now(),
        ]);
    }
}


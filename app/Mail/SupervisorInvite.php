<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use App\Models\User;

class SupervisorInvite extends Mailable
{
    public function __construct(protected User $user, protected string $url) {}

    public function build()
    {
        return $this->subject('Benvenuto! Imposta la tua password')
                    ->view('emails.supervisor-invite')
                    ->with([
                      'name' => $this->user->firstname,
                      'resetUrl' => $this->url,
                    ]);
    }
}

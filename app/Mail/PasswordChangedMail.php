<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Security notice after a reset or a change. Always sent, regardless of alert settings. */
class PasswordChangedMail extends Mailable
{
    public string $name;
    public string $when;
    public string $resetUrl;

    /**
     * @param  bool  $keptThisDevice  true for a change made in the app, where the
     *                                phone that made it stays signed in
     */
    public function __construct(User $user, public bool $keptThisDevice = false)
    {
        $this->name = $user->shortName();
        $this->when = now(config('atompay.notifications.timezone'))->format('j M Y, g:i a');
        $this->resetUrl = route('password.request');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your AtomPay password was changed');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-changed');
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent once, when an account is created on the website or in the app. */
class WelcomeMail extends Mailable
{
    public string $name;
    public string $applyUrl;

    public function __construct(User $user)
    {
        $this->name = $user->shortName();
        $this->applyUrl = route('account.application');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to AtomPay');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome');
    }
}

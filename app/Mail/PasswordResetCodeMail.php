<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The one-time code for a password reset. Always sent, regardless of alert settings. */
class PasswordResetCodeMail extends Mailable
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your AtomPay code");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-code');
    }
}

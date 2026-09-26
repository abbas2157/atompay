<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The code that proves a new customer owns the email address they are signing up with. */
class SignupCodeMail extends Mailable
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->code} is your AtomPay sign-up code");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signup-code');
    }
}

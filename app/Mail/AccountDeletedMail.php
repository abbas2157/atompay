<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation after self-service deletion. Takes plain strings, not the
 * User: by the time a queued copy runs, that row has been anonymised.
 */
class AccountDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $when;

    public function __construct(public string $name)
    {
        $this->when = now(config('atompay.notifications.timezone'))->format('j M Y, g:i a');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your AtomPay account has been deleted');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-deleted');
    }
}

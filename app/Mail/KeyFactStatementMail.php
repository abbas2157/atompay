<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A copy of an order's Key Fact Statement, sent when the customer asks for one in the app. */
class KeyFactStatementMail extends Mailable
{
    /** @param array<string, mixed> $kfs from KeyFactStatementService::forOrder() */
    public function __construct(public string $name, public array $kfs) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Key Fact Statement for order '.$this->kfs['order_number']);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.key-fact-statement');
    }
}

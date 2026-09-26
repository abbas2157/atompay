<?php

namespace App\Mail;

use App\Models\CustomerNotification;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * The email copy of an inbox notification (limit decided, KYC outcome,
 * instalment reminder, application received). Carries a one-click
 * unsubscribe (RFC 8058), which Gmail and Yahoo expect from senders.
 */
class CustomerAlertMail extends Mailable
{
    public string $name;
    public string $title;
    public string $body;
    public string $eyebrow;
    public string $toneColor;
    public string $actionUrl;
    public string $actionLabel;
    public string $unsubscribeUrl;

    public function __construct(CustomerNotification $notification, User $user)
    {
        $this->name = $user->shortName();
        $this->title = $notification->title;
        $this->body = $notification->body;

        [$this->eyebrow, $this->toneColor] = match ($notification->type) {
            CustomerNotification::TYPE_INSTALMENT_OVERDUE, CustomerNotification::TYPE_KYC_REJECTED => ['Action needed', '#D1394B'],
            CustomerNotification::TYPE_INSTALMENT_DUE => ['Payment reminder', '#B86E0C'],
            default => ['AtomPay update', '#1E9E6A'],
        };

        // Web pages for each app screen. The web dashboard shows plans too.
        [$this->actionUrl, $this->actionLabel] = match ($notification->data['screen'] ?? 'dashboard') {
            'profile' => [route('account.application'), 'Review my details'],
            'plan' => [route('account.dashboard'), 'View my payments'],
            default => [route('account.dashboard'), 'Open My AtomPay'],
        };

        $this->unsubscribeUrl = URL::signedRoute('email.alerts.off', ['user' => $user->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.alert');
    }
}

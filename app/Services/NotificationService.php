<?php

namespace App\Services;

use App\Mail\CustomerAlertMail;
use App\Models\CustomerNotification;
use App\Models\Device;
use App\Models\User;
use App\Services\Push\FcmClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells a customer something: an inbox row first, then a push to each of
 * their registered phones and an email. The dedupe key makes every
 * announcement happen at most once, however often the sweep runs.
 */
class NotificationService
{
    public function __construct(private readonly FcmClient $fcm) {}

    /**
     * @param array<string, scalar|null> $data deep-link payload for the app (screen, ids)
     * @return CustomerNotification|null null when this key was already announced
     */
    public function notify(User $user, string $type, string $title, string $body, array $data, string $dedupeKey): ?CustomerNotification
    {
        if (CustomerNotification::where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        try {
            $notification = CustomerNotification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'dedupe_key' => $dedupeKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;   // a concurrent sweep got there first
        }

        $this->push($notification);
        $this->email($notification, $user);

        return $notification;
    }

    /**
     * The same message by email, unless the customer turned alert emails off.
     * A mail failure is logged, never thrown: the inbox row and push already
     * happened, and the sweep must carry on to the next customer.
     */
    public function email(CustomerNotification $notification, User $user): void
    {
        if (! $user->hasRealEmail() || ! $user->wantsEmailAlerts()) {
            return;
        }

        try {
            Mail::to($user->email, $user->name)->send(new CustomerAlertMail($notification, $user));
            $notification->forceFill(['emailed_at' => now()])->save();
        } catch (Throwable $e) {
            Log::error('AtomPay alert email failed', ['notification_id' => $notification->id, 'error' => $e->getMessage()]);
        }
    }

    /** Sends to every device; drops devices FCM says are gone. */
    public function push(CustomerNotification $notification): void
    {
        if (! $this->fcm->enabled()) {
            return;
        }

        $delivered = false;
        $payload = [...($notification->data ?? []), 'notification_id' => $notification->id, 'type' => $notification->type];

        foreach (Device::where('user_id', $notification->user_id)->get() as $device) {
            $result = $this->fcm->send($device->fcm_token, $notification->title, $notification->body, $payload);

            if ($result === FcmClient::INVALID_TOKEN) {
                $device->delete();
            }
            $delivered = $delivered || $result === FcmClient::SENT;
        }

        if ($delivered) {
            $notification->forceFill(['pushed_at' => now()])->save();
        }
    }
}

<?php

namespace App\Services;

use App\Models\CustomerNotification;
use App\Models\Device;
use App\Models\User;
use App\Services\Push\FcmClient;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Tells a customer something: an inbox row first, then a push to each of
 * their registered phones. The dedupe key makes every announcement
 * happen at most once, however often the sweep runs.
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

        return $notification;
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

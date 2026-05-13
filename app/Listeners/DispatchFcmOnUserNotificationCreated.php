<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserNotificationCreated;
use App\Jobs\SendPushNotification;
use App\Models\User;

/**
 * Mirrors every {@see UserNotificationCreated} (Pusher/Echo) broadcast with an FCM device push when the user has a token.
 */
final class DispatchFcmOnUserNotificationCreated
{
    public function handle(UserNotificationCreated $event): void
    {
        $notification = $event->notification;
        $recipient = User::query()->find($notification->user_id);
        if ($recipient === null) {
            return;
        }

        $data = $notification->data ?? [];
        $data['notification_type'] = $notification->type;

        // Dispatch synchronously so device push does not depend on a separate
        // notifications queue worker being alive.
        SendPushNotification::dispatchSync(
            $recipient,
            (string) ($notification->title ?? ''),
            (string) ($notification->body ?? ''),
            $data,
        );
    }
}

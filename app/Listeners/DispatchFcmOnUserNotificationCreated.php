<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserNotificationCreated;
use App\Jobs\SendPushNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

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

        try {
            SendPushNotification::dispatch(
                $recipient,
                (string) ($notification->title ?? ''),
                (string) ($notification->body ?? ''),
                $data,
            );
        } catch (\Throwable $e) {
            Log::error('fcm.dispatch_failed', [
                'notification_id' => $notification->id,
                'recipient_id' => $recipient->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}

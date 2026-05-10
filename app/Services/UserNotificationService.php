<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\UserNotificationCreated;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

class UserNotificationService
{
    /**
     * Persist a notification only (no realtime broadcast). Use {@see broadcast()} or {@see notify()} to push to Pusher.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(
        User $recipient,
        string $type,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
        ?User $sender = null,
    ): UserNotification {
        return DB::transaction(function () use ($recipient, $type, $title, $body, $data, $actionUrl, $sender): UserNotification {
            return $this->persistRecord($recipient, $type, $title, $body, $data, $actionUrl, $sender);
        });
    }

    /**
     * Queue a broadcast for an existing notification (Pusher / Echo). Idempotent at the domain level; safe to call after {@see create()}.
     */
    public function broadcast(UserNotification $notification): void
    {
        UserNotificationCreated::dispatch($notification);
    }

    /**
     * Create and broadcast in one transaction (typical “send notification” flow).
     *
     * @param  array<string, mixed>  $data
     */
    public function notify(
        User $recipient,
        string $type,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
        ?User $sender = null,
    ): UserNotification {
        return DB::transaction(function () use ($recipient, $type, $title, $body, $data, $actionUrl, $sender): UserNotification {
            $notification = $this->persistRecord($recipient, $type, $title, $body, $data, $actionUrl, $sender);

            UserNotificationCreated::dispatch($notification);

            return $notification;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(
        User $user,
        string $type,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
        ?User $sender = null,
    ): UserNotification {
        return $this->notify($user, $type, $title, $body, $data, $actionUrl, $sender);
    }

    public function markAsRead(UserNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAsUnread(UserNotification $notification): void
    {
        $notification->markAsUnread();
    }

    public function markAllAsRead(User $user): int
    {
        return $user->userNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Soft-delete a notification for the recipient (hide from inbox only).
     */
    public function dismiss(UserNotification $notification): void
    {
        $notification->delete();
    }

    /**
     * Fan-out one in-app notification per user with the given role (each row broadcasts separately).
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyUsersByRole(
        UserRole $role,
        string $type,
        ?string $title = null,
        ?string $body = null,
        array $data = [],
        ?string $actionUrl = null,
        ?User $sender = null,
    ): int {
        $count = 0;

        User::query()
            ->where('role', $role->value)
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$count, $type, $title, $body, $data, $actionUrl, $sender): void {
                foreach ($users as $user) {
                    $this->notify($user, $type, $title, $body, $data, $actionUrl, $sender);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRecord(
        User $recipient,
        string $type,
        ?string $title,
        ?string $body,
        array $data,
        ?string $actionUrl,
        ?User $sender,
    ): UserNotification {
        return UserNotification::query()->create([
            'user_id' => $recipient->id,
            'sender_id' => $sender?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
            'action_url' => $actionUrl,
        ]);
    }
}

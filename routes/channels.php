<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function (User $user, string $userId): bool {
    return (int) $user->getAuthIdentifier() === (int) $userId;
}, ['guards' => ['api']]);

Broadcast::channel('notifications.{userId}', function (User $user, string $userId): bool {
    return (int) $user->getAuthIdentifier() === (int) $userId;
}, ['guards' => ['api']]);

Broadcast::channel('chat.room.{conversationId}', function (User $user, string $conversationId): bool {
    $conversation = Conversation::query()->find($conversationId);

    return $conversation !== null && $conversation->hasParticipant($user);
}, ['guards' => ['api']]);

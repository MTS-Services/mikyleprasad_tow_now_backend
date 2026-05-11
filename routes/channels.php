<?php

use App\Models\Conversation;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('ride.{rideId}', function (User $user, string $rideId): bool {
    $ride = Ride::query()->find($rideId);

    if ($ride === null) {
        return false;
    }

    return (int) $ride->user_id === (int) $user->getAuthIdentifier()
        || (int) $ride->driver_id === (int) $user->getAuthIdentifier();
}, ['guards' => ['api']]);

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

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;

final class ConversationUniquenessService
{
    /**
     * @param  array<int, int>  $participantIds
     */
    public function findExactParticipantMatch(array $participantIds, ?int $ignoreConversationId = null): ?Conversation
    {
        $ids = collect($participantIds)->map(intval(...))->unique()->values()->all();
        $count = count($ids);

        if ($count < 2 || ! $this->isEnabledForParticipantCount($count)) {
            return null;
        }

        $query = Conversation::query();

        if ($ignoreConversationId !== null) {
            $query->whereKeyNot($ignoreConversationId);
        }

        return $query
            ->whereHas('participants', fn ($q) => $q->whereIn('users.id', $ids), '=', $count)
            ->whereDoesntHave('participants', fn ($q) => $q->whereNotIn('users.id', $ids))
            ->first();
    }

    private function isEnabledForParticipantCount(int $participantCount): bool
    {
        $groupEnabled = (bool) config('messaging.conversation_uniqueness.group_participants', false);
        $twoEnabled = (bool) config('messaging.conversation_uniqueness.two_participants', false);

        if ($participantCount > 2) {
            return $groupEnabled;
        }

        if ($participantCount === 2) {
            return $twoEnabled || $groupEnabled;
        }

        return false;
    }
}

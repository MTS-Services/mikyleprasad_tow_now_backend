<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_unread' => $this->when(
                array_key_exists('is_unread', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('is_unread')
            ),
            'last_message_sent_at' => $this->when(
                array_key_exists('last_message_sent_at', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('last_message_sent_at')
            ),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator === null ? null : [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
                'role' => $this->creator->role->value,
            ]),
            'participants' => $this->whenLoaded('participants', function () {
                return $this->participants->map(function (User $user): array {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role->value,
                    ];
                });
            }),
            'activity_logs' => $this->whenLoaded('activityLogs', function () {
                return $this->activityLogs->map(function ($log): array {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'data' => $log->data,
                        'created_at' => $log->created_at?->toIso8601String(),
                        'actor' => $log->relationLoaded('actor') && $log->actor !== null ? [
                            'id' => $log->actor->id,
                            'name' => $log->actor->name,
                            'email' => $log->actor->email,
                        ] : null,
                    ];
                });
            }),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddConversationParticipantsRequest;
use App\Http\Requests\Api\V1\StoreConversationRequest;
use App\Http\Requests\Api\V1\UpdateConversationRequest;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use App\Models\ConversationActivityLog;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Services\ConversationUniquenessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationUniquenessService $conversationUniquenessService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $userId = (int) $request->user()->id;

        $conversations = Conversation::query()
            ->forParticipant($request->user())
            ->addSelect([
                'last_message_id' => Message::query()
                    ->select('id')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->orderByDesc('id')
                    ->limit(1),
                'last_message_sent_at' => Message::query()
                    ->select('created_at')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->orderByDesc('id')
                    ->limit(1),
                'auth_last_read_message_id' => ConversationParticipant::query()
                    ->select('last_read_message_id')
                    ->whereColumn('conversation_participants.conversation_id', 'conversations.id')
                    ->where('conversation_participants.user_id', $userId)
                    ->limit(1),
            ])
            ->selectRaw(
                'CASE
                    WHEN (
                        SELECT MAX(m2.id)
                        FROM messages m2
                        WHERE m2.conversation_id = conversations.id
                    ) IS NULL THEN 0
                    WHEN (
                        SELECT cp.last_read_message_id
                        FROM conversation_participants cp
                        WHERE cp.conversation_id = conversations.id
                          AND cp.user_id = ?
                        LIMIT 1
                    ) IS NULL THEN 1
                    WHEN (
                        SELECT MAX(m3.id)
                        FROM messages m3
                        WHERE m3.conversation_id = conversations.id
                    ) > (
                        SELECT cp2.last_read_message_id
                        FROM conversation_participants cp2
                        WHERE cp2.conversation_id = conversations.id
                          AND cp2.user_id = ?
                        LIMIT 1
                    ) THEN 1
                    ELSE 0
                END as is_unread',
                [$userId, $userId]
            )
            ->with(['participants', 'creator'])
            ->orderByDesc('last_message_sent_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return sendResponse(
            status: true,
            message: __('api.conversations_fetched_successfully'),
            data: ConversationResource::collection($conversations),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $conversation->hasParticipant($request->user())) {
            return sendResponse(
                status: false,
                message: __('api.conversation_forbidden'),
                data: null,
                statusCode: HttpStatus::HTTP_FORBIDDEN
            );
        }

        $conversation->load([
            'participants',
            'creator',
            'activityLogs' => fn ($query) => $query->limit(20),
            'activityLogs.actor',
        ]);

        return sendResponse(
            status: true,
            message: __('api.conversation_fetched_successfully'),
            data: new ConversationResource($conversation),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $name = $request->validated('name');
        $participantIds = collect($request->validated('participant_ids'))
            ->map(intval(...))
            ->unique()
            ->values()
            ->all();

        $existingConversation = $this->conversationUniquenessService->findExactParticipantMatch($participantIds);

        if ($existingConversation !== null) {
            throw ValidationException::withMessages([
                'participant_ids' => [
                    __('api.conversation_unique_participants_exists', ['id' => $existingConversation->id]),
                ],
            ]);
        }

        $conversation = DB::transaction(function () use ($participantIds, $name, $request): Conversation {
            $conversation = Conversation::query()->create([
                'name' => $name,
                'created_by' => $request->user()->id,
            ]);
            $conversation->participants()->attach($participantIds);
            $this->logActivity($conversation, $request->user()->id, 'conversation.created', [
                'name' => $name,
                'participant_ids' => $participantIds,
            ]);

            return $conversation;
        });

        $conversation->load(['participants', 'creator']);

        return sendResponse(
            status: true,
            message: __('api.conversation_created_successfully'),
            data: new ConversationResource($conversation),
            statusCode: HttpStatus::HTTP_CREATED
        );
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $conversation->forceFill([
            'name' => $request->validated('name'),
        ])->save();
        $this->logActivity($conversation, $request->user()->id, 'conversation.renamed', [
            'name' => $conversation->name,
        ]);

        $conversation->load(['participants', 'creator']);

        return sendResponse(
            status: true,
            message: __('api.conversation_updated_successfully'),
            data: new ConversationResource($conversation),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    /**
     * @throws ValidationException
     */
    public function addParticipants(AddConversationParticipantsRequest $request, Conversation $conversation): JsonResponse
    {
        $userIds = collect($request->validated('participant_ids'))
            ->map(intval(...))
            ->unique()
            ->values();

        $existingParticipantIds = $conversation->participants()
            ->whereIn('users.id', $userIds->all())
            ->pluck('users.id');

        if ($existingParticipantIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'participant_ids' => [
                    __('api.conversation_participants_already_member'),
                ],
            ]);
        }

        $targetParticipantIds = $conversation->participants()
            ->pluck('users.id')
            ->merge($userIds)
            ->map(intval(...))
            ->unique()
            ->values()
            ->all();

        $duplicateConversation = $this->conversationUniquenessService->findExactParticipantMatch(
            $targetParticipantIds,
            $conversation->id
        );

        if ($duplicateConversation !== null) {
            throw ValidationException::withMessages([
                'participant_ids' => [
                    __('api.conversation_unique_participants_exists', ['id' => $duplicateConversation->id]),
                ],
            ]);
        }

        $conversation->participants()->attach($userIds->all());
        $this->logActivity($conversation, $request->user()->id, 'participants.added', [
            'participant_ids' => $userIds->all(),
        ]);
        $conversation->load(['participants', 'creator']);

        return sendResponse(
            status: true,
            message: __('api.conversation_participants_added_successfully'),
            data: new ConversationResource($conversation),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function logActivity(Conversation $conversation, int $actorId, string $action, array $data = []): void
    {
        ConversationActivityLog::query()->create([
            'conversation_id' => $conversation->id,
            'actor_id' => $actorId,
            'action' => $action,
            'data' => $data === [] ? null : $data,
        ]);
    }
}

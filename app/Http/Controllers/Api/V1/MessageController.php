<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Services\ConversationMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class MessageController extends Controller
{
    public function __construct(
        private readonly ConversationMessageService $conversationMessageService,
    ) {}

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $body = $request->validated('body');
        $attachments = $request->file('attachments', []);

        $message = $this->conversationMessageService->sendUserMessage(
            $conversation,
            $request->user(),
            $body,
            $attachments
        );

        $message->load(['sender', 'attachments']);

        return sendResponse(
            status: true,
            message: __('api.message_sent_successfully'),
            data: new MessageResource($message),
            statusCode: HttpStatus::HTTP_CREATED
        );
    }

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        if (! $conversation->hasParticipant($request->user())) {
            return sendResponse(
                status: false,
                message: __('api.conversation_forbidden'),
                data: null,
                statusCode: HttpStatus::HTTP_FORBIDDEN
            );
        }

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'before_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $beforeId = isset($validated['before_id']) ? (int) $validated['before_id'] : null;

        $query = $conversation->messages()
            ->with(['sender', 'attachments'])
            ->orderByDesc('id');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit($perPage + 1)->get();
        $hasMoreOlder = $messages->count() > $perPage;
        $messagesPage = $messages->take($perPage)->values();
        $nextBeforeId = $hasMoreOlder && $messagesPage->isNotEmpty()
            ? (int) $messagesPage->last()->id
            : null;

        if ($beforeId === null) {
            $latestMessageId = (int) ($conversation->messages()->max('id') ?? 0);

            if ($latestMessageId > 0) {
                ConversationParticipant::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $request->user()->id)
                    ->update([
                        'last_read_message_id' => $latestMessageId,
                        'last_read_at' => now(),
                    ]);
            }
        }

        return sendResponse(
            status: true,
            message: __('api.messages_fetched_successfully'),
            data: MessageResource::collection($messagesPage),
            statusCode: HttpStatus::HTTP_OK,
            additional: [
                'meta' => [
                    'per_page' => $perPage,
                    'before_id' => $beforeId,
                    'has_more_older' => $hasMoreOlder,
                    'next_before_id' => $nextBeforeId,
                ],
            ]
        );
    }
}

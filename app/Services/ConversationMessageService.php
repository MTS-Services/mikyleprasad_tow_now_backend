<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\Realtime\RealtimeBroadcastDispatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ConversationMessageService
{
    public function __construct(
        private readonly RealtimeBroadcastDispatcher $realtimeBroadcastDispatcher,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function sendUserMessage(Conversation $conversation, User $sender, ?string $body, array $attachments = []): Message
    {
        return DB::transaction(function () use ($conversation, $sender, $body, $attachments): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $this->normalizeBody($body),
            ]);

            $this->storeAttachments($conversation, $message, $attachments);

            $message->load(['sender', 'attachments']);

            $this->realtimeBroadcastDispatcher->queue(new MessageSent($message));

            return $message;
        });
    }

    private function normalizeBody(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $trimmed = trim($body);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    private function storeAttachments(Conversation $conversation, Message $message, array $attachments): void
    {
        if ($attachments === []) {
            return;
        }

        $disk = (string) Config::get('messaging.attachments.disk', 'public');

        foreach ($attachments as $attachment) {
            $path = $attachment->store('message-attachments/'.$conversation->id.'/'.$message->id, [
                'disk' => $disk,
            ]);

            MessageAttachment::query()->create([
                'message_id' => $message->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $attachment->getClientOriginalName(),
                'mime_type' => $attachment->getClientMimeType(),
                'size_bytes' => $attachment->getSize() ?? Storage::disk($disk)->size($path),
            ]);
        }
    }
}

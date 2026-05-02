<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MessageAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'message_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
])]
class MessageAttachment extends Model
{
    /** @use HasFactory<MessageAttachmentFactory> */
    use HasFactory;

    protected $appends = ['url'];

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! is_string($this->path) || $this->path === '') {
            return null;
        }

        if (filter_var($this->path, FILTER_VALIDATE_URL) && str_starts_with($this->path, 'https://')) {
            return $this->path;
        }

        return Storage::disk((string) $this->disk)->url($this->path);
    }
}

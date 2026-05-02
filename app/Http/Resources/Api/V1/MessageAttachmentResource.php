<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MessageAttachment
 */
class MessageAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => $this->url,
        ];
    }
}

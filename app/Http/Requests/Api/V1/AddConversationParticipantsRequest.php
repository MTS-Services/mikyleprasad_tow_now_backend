<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Conversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AddConversationParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Conversation|null $conversation */
        $conversation = $this->route('conversation');

        if ($conversation === null) {
            return false;
        }

        return $conversation->hasParticipant($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['distinct', 'integer', 'exists:users,id'],
        ];
    }
}

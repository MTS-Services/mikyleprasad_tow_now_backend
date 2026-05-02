<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Conversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConversationRequest extends FormRequest
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
            'name' => ['required', 'nullable', 'string', 'max:255'],
        ];
    }
}

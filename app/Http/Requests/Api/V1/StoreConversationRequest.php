<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ids = $this->input('participant_ids');

        if (! is_array($ids)) {
            return false;
        }

        $normalized = array_map(intval(...), $ids);

        return in_array((int) $this->user()->id, $normalized, true);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:2'],
            'participant_ids.*' => ['distinct', 'integer', 'exists:users,id'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Conversation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreMessageRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:20000'],
            'attachments' => ['sometimes', 'array', 'max:'.config('messaging.attachments.max_per_message', 5)],
            'attachments.*' => [
                'file',
                File::types(config('messaging.attachments.allowed_extensions', []))
                    ->max(config('messaging.attachments.max_file_kb', 10240)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $body = trim((string) $this->input('body', ''));
            $attachments = $this->file('attachments', []);

            if ($body === '' && count($attachments) === 0) {
                $v->errors()->add('body', __('api.message_body_or_attachment_required'));
            }
        });
    }
}

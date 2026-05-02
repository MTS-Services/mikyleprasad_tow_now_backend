<?php

namespace App\Actions\Api\V1\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\UserLoginHistoryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterUserAction
{
    public function __construct(
        protected IssuePersonalAccessTokenAction $issuePersonalAccessTokenAction,
        protected UserLoginHistoryRecorder $userLoginHistoryRecorder,
    ) {}

    /**
     * @return array{user: User, access_token: string, token_type: string}
     *
     * @throws ValidationException
     */
    public function handle(Request $request): array
    {
        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['nullable', 'string', 'max:24'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'nullable', 'string', Rule::in([UserRole::USER->value])],
        ])->validate();

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'locale' => $validated['locale'] ?? 'en',
            'password' => $validated['password'],
            'role' => UserRole::USER,
        ]);

        $accessToken = $this->issuePersonalAccessTokenAction->handle(
            $user,
            $validated['device_name'] ?? null
        );

        $user = $user->fresh();
        $this->userLoginHistoryRecorder->record($user, $request);

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
        ];
    }
}

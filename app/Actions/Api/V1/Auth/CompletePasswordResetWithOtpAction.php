<?php

namespace App\Actions\Api\V1\Auth;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Otp\OtpRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CompletePasswordResetWithOtpAction
{
    public function __construct(
        protected OtpRepository $otpRepository,
        protected RevokePassportTokensAction $revokePassportTokensAction,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $plainCode = $request->string('code')->toString();
        $fingerprint = OtpRepository::fingerprint('password_reset', $email);
        $stored = $this->otpRepository->get(OtpPurpose::PasswordReset, $fingerprint);
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $stored === null || $stored['user_id'] !== $user->id) {
            throw ValidationException::withMessages([
                'code' => [__('passwords.token')],
            ]);
        }

        $actual = OtpRepository::hashCode($plainCode);

        if (! hash_equals($stored['hash'], $actual)) {
            throw ValidationException::withMessages([
                'code' => [__('passwords.token')],
            ]);
        }

        $this->otpRepository->forget(OtpPurpose::PasswordReset, $fingerprint);

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        $this->revokePassportTokensAction->handle($user);

        return sendResponse(
            status: true,
            message: __('passwords.reset'),
            data: null,
            statusCode: HttpStatus::HTTP_OK
        );
    }
}

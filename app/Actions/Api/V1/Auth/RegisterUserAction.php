<?php

namespace App\Actions\Api\V1\Auth;

use App\Enums\ApiErrorCode;
use App\Enums\LoginIdentifierType;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Models\DriverProfile;
use App\Models\User;
use App\Notifications\Auth\OtpCodeNotification;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Auth\LoginIdentifierDetector;
use App\Services\Auth\UserLoginHistoryRecorder;
use App\Services\Otp\OtpRepository;
use App\Support\Auth\GuestToken;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RegisterUserAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected OtpRepository $otpRepository,
        protected IssuePersonalAccessTokenAction $issuePersonalAccessTokenAction,
        protected UserLoginHistoryRecorder $userLoginHistoryRecorder,
    ) {}

    /**
     * @return array{user: User, identifier_type: string, identifier: string, verification_channel: string, expires_in_minutes: int}
     *
     * @throws ValidationException
     */
    public function handle(Request $request): array
    {
        $validated = $this->validatedRegistrationPayload($request);
        $verificationChannel = $this->verificationChannelForRegistration();
        $this->ensureVerificationDeliveryIsAvailable($verificationChannel);
        $guestTokenHash = GuestToken::hash(GuestToken::fromRequest($request));

        $identifierType = $verificationChannel === 'phone' ? LoginIdentifierType::Phone : LoginIdentifierType::Email;
        $identifier = $this->registrationIdentifier($identifierType, $validated);
        $user = $this->createUserFromValidatedPayload($request, $validated);

        $this->sendVerificationOtp($user, $identifierType, $identifier, $verificationChannel, $guestTokenHash);

        return [
            'user' => $user->fresh(['driverProfile']),
            'identifier_type' => $identifierType->value,
            'identifier' => $identifier,
            'verification_channel' => $verificationChannel,
            'expires_in_minutes' => $this->authLogin->otpTtlMinutes(),
        ];
    }

    /**
     * @return array{verification_channel: string, expires_in_minutes: int}
     */
    public function resend(Request $request): array
    {
        [$identifierType, $identifier] = $this->registrationIdentifierFromRequest($request);
        $guestTokenHash = GuestToken::hash(GuestToken::fromRequest($request));
        $user = $this->findUserByIdentifier($identifierType, $identifier);

        if ($user === null) {
            throw ValidationException::withMessages([
                $identifierType->value => [__('api.no_account_exists_for_this_sign_in')],
            ]);
        }

        $verificationChannel = $identifierType === LoginIdentifierType::Phone ? 'phone' : 'email';
        $this->ensureVerificationDeliveryIsAvailable($verificationChannel);
        $this->sendVerificationOtp($user, $identifierType, $identifier, $verificationChannel, $guestTokenHash);

        return [
            'verification_channel' => $verificationChannel,
            'expires_in_minutes' => $this->authLogin->otpTtlMinutes(),
        ];
    }

    /**
     * @return array{user: User, access_token: string, token_type: string}
     */
    public function verify(Request $request): array
    {
        [$identifierType, $identifier] = $this->registrationIdentifierFromRequest($request);
        $fingerprint = OtpRepository::fingerprint($identifierType->value, $identifier);
        $purpose = $identifierType === LoginIdentifierType::Phone ? OtpPurpose::VerifyPhone : OtpPurpose::VerifyEmail;
        $stored = $this->otpRepository->get($purpose, $fingerprint);

        if ($stored === null) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        GuestToken::assertMatches($request, $stored['guest_token_hash'] ?? null);

        if (! hash_equals($stored['hash'], OtpRepository::hashCode($request->string('code')->toString()))) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $user = $this->findUserByIdentifier($identifierType, $identifier);

        if ($user === null || (int) $stored['user_id'] !== (int) $user->id) {
            $this->otpRepository->forget($purpose, $fingerprint);
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $this->otpRepository->forget($purpose, $fingerprint);
        $this->markIdentifierVerified($user, $identifierType);

        $accessToken = $this->issuePersonalAccessTokenAction->handle(
            $user,
            $request->string('device_name')->toString() ?: null
        );

        $this->userLoginHistoryRecorder->record($user, $request);

        return [
            'user' => $user->fresh(['driverProfile']),
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRegistrationPayload(Request $request): array
    {
        return Validator::make($request->all(), [
            'role' => ['required', 'string', Rule::in([UserRole::USER->value, UserRole::DRIVER->value])],
            'name' => ['required_if:role,'.UserRole::DRIVER->value, 'nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => [
                'required_if:role,'.UserRole::DRIVER->value,
                'nullable',
                'string',
                'max:32',
                Rule::unique(User::class),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'locale' => ['nullable', 'string', 'max:24'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'car_brand' => ['required_if:role,'.UserRole::DRIVER->value, 'nullable', 'string', 'max:255'],
            'car_model' => ['required_if:role,'.UserRole::DRIVER->value, 'nullable', 'string', 'max:255'],
            'car_type' => ['required_if:role,'.UserRole::DRIVER->value, 'nullable', 'string', 'max:255'],
            'license_plate' => [
                'required_if:role,'.UserRole::DRIVER->value,
                'nullable',
                'string',
                'max:64',
                Rule::unique(DriverProfile::class),
            ],
            'location' => ['required_if:role,'.UserRole::DRIVER->value, 'nullable', 'string', 'max:500'],
            'truck_image' => [
                'required_if:role,'.UserRole::DRIVER->value,
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'driving_license_image' => [
                'required_if:role,'.UserRole::DRIVER->value,
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'car_legal_documents' => [
                'required_if:role,'.UserRole::DRIVER->value,
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ])->validate();
    }

    /**
     * @return 'email'|'phone'
     */
    private function verificationChannelForRegistration(): string
    {
        $identifiers = $this->authLogin->loginIdentifierTypes();

        if (count($identifiers) === 1 && $identifiers[0] === LoginIdentifierType::Phone) {
            return 'phone';
        }

        return 'email';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function registrationIdentifier(LoginIdentifierType $identifierType, array $validated): string
    {
        return match ($identifierType) {
            LoginIdentifierType::Email => strtolower((string) $validated['email']),
            LoginIdentifierType::Phone => (string) $validated['phone'],
            LoginIdentifierType::Username => strtolower((string) $validated['username']),
        };
    }

    private function sendVerificationOtp(
        User $user,
        LoginIdentifierType $identifierType,
        string $identifier,
        string $channel,
        string $guestTokenHash
    ): string {
        $length = $this->authLogin->otpCodeLength();
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $plainCode = (string) random_int((int) $min, (int) $max);

        $purpose = $channel === 'phone' ? OtpPurpose::VerifyPhone : OtpPurpose::VerifyEmail;
        $fingerprint = OtpRepository::fingerprint($identifierType->value, $identifier);

        $this->otpRepository->put(
            $purpose,
            $fingerprint,
            [
                'user_id' => $user->id,
                'hash' => OtpRepository::hashCode($plainCode),
                'guest_token_hash' => $guestTokenHash,
            ],
            $this->authLogin->otpTtlMinutes()
        );

        if ($channel === 'email') {
            $user->notify(new OtpCodeNotification($plainCode, $purpose));
        }

        return $channel;
    }

    private function ensureVerificationDeliveryIsAvailable(string $channel): void
    {
        if ($channel !== 'phone') {
            return;
        }

        throw new HttpResponseException(sendResponse(
            status: false,
            message: __('api.sms_otp_not_available'),
            data: null,
            statusCode: HttpStatus::HTTP_SERVICE_UNAVAILABLE,
            additional: ['code' => ApiErrorCode::SmsOtpNotAvailable->value]
        ));
    }

    /**
     * @return array{0: LoginIdentifierType, 1: string}
     */
    private function registrationIdentifierFromRequest(Request $request): array
    {
        $identifiers = $this->authLogin->loginIdentifierTypes();

        if (count($identifiers) > 1) {
            $identifier = trim($request->string('identifier')->toString());

            if ($identifier === '') {
                throw ValidationException::withMessages([
                    'identifier' => [__('validation.required', ['attribute' => 'identifier'])],
                ]);
            }

            return app(LoginIdentifierDetector::class)->resolve(null, $identifier, $identifiers);
        }

        $type = $identifiers[0];
        $field = $type->value;
        $identifier = trim($request->string('identifier')->toString() ?: $request->string($field)->toString());

        if ($identifier === '') {
            throw ValidationException::withMessages([
                $field => [__('validation.required', ['attribute' => $field])],
            ]);
        }

        if ($type === LoginIdentifierType::Email && ! filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                $field => [__('validation.email', ['attribute' => $field])],
            ]);
        }

        return [$type, $type === LoginIdentifierType::Email ? strtolower($identifier) : $identifier];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function createUserFromValidatedPayload(Request $request, array $validated): User
    {
        $role = UserRole::from((string) $validated['role']);

        return DB::transaction(function () use ($request, $validated, $role): User {
            $user = User::query()->create([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'locale' => $validated['locale'] ?? 'en',
                'password' => $validated['password'] ?? null,
                'role' => $role,
            ]);

            if ($role === UserRole::DRIVER) {
                DriverProfile::query()->create([
                    'user_id' => $user->id,
                    'car_brand' => $validated['car_brand'],
                    'car_model' => $validated['car_model'],
                    'car_type' => $validated['car_type'],
                    'license_plate' => $validated['license_plate'],
                    'location' => $validated['location'],
                    'truck_image_path' => $request->file('truck_image')->store('driver-profiles/trucks', 'public'),
                    'driving_license_image_path' => $request->file('driving_license_image')->store('driver-profiles/licenses', 'public'),
                    'car_legal_documents_path' => $request->file('car_legal_documents')->store('driver-profiles/documents', 'public'),
                ]);
            }

            return $user;
        });
    }

    private function findUserByIdentifier(LoginIdentifierType $identifierType, string $identifier): ?User
    {
        return match ($identifierType) {
            LoginIdentifierType::Email => User::query()->where('email', $identifier)->first(),
            LoginIdentifierType::Phone => User::query()->where('phone', $identifier)->first(),
            LoginIdentifierType::Username => User::query()->where('username', $identifier)->first(),
        };
    }

    private function markIdentifierVerified(User $user, LoginIdentifierType $identifierType): void
    {
        if ($identifierType === LoginIdentifierType::Email) {
            $user->forceFill(['email_verified_at' => now()])->save();

            return;
        }

        if ($identifierType === LoginIdentifierType::Phone) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }
    }
}

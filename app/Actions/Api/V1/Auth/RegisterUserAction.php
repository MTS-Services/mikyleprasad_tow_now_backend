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
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class RegisterUserAction
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected IssuePersonalAccessTokenAction $issuePersonalAccessTokenAction,
        protected UserLoginHistoryRecorder $userLoginHistoryRecorder,
    ) {}

    /**
     * @return array{identifier_type: string, identifier: string, verification_channel: string, expires_in_minutes: int}
     *
     * @throws ValidationException
     */
    public function handle(Request $request): array
    {
        $validated = $this->validatedRegistrationPayload($request);
        $verificationChannel = $this->verificationChannelForRegistration();
        $this->ensureVerificationDeliveryIsAvailable($verificationChannel);

        $identifierType = $verificationChannel === 'phone' ? LoginIdentifierType::Phone : LoginIdentifierType::Email;
        $identifier = $this->registrationIdentifier($identifierType, $validated);
        $pending = $this->pendingPayload($request, $validated);

        Cache::put(
            $this->pendingRegistrationCacheKey($identifierType, $identifier),
            $pending,
            now()->addMinutes($this->authLogin->otpTtlMinutes())
        );

        $this->sendVerificationOtp($identifierType, $identifier, $verificationChannel);

        return [
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
        $pending = Cache::get($this->pendingRegistrationCacheKey($identifierType, $identifier));

        if (! is_array($pending)) {
            throw ValidationException::withMessages([
                $identifierType->value => [__('api.pending_registration_not_found')],
            ]);
        }

        $verificationChannel = $identifierType === LoginIdentifierType::Phone ? 'phone' : 'email';
        $this->ensureVerificationDeliveryIsAvailable($verificationChannel);
        $this->sendVerificationOtp($identifierType, $identifier, $verificationChannel);

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
        $otpKey = $this->registrationOtpCacheKey($fingerprint);
        $stored = Cache::get($otpKey);

        if (! is_array($stored) || ! isset($stored['hash']) || ! is_string($stored['hash'])) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        if (! hash_equals($stored['hash'], OtpRepository::hashCode($request->string('code')->toString()))) {
            throw ValidationException::withMessages([
                'code' => [__('api.otp_invalid_or_expired')],
            ]);
        }

        $pendingKey = $this->pendingRegistrationCacheKey($identifierType, $identifier);
        $pending = Cache::get($pendingKey);

        if (! is_array($pending)) {
            Cache::forget($otpKey);

            throw ValidationException::withMessages([
                $identifierType->value => [__('api.pending_registration_not_found')],
            ]);
        }

        $user = $this->createUserFromPendingPayload($pending, $identifierType);

        Cache::forget($otpKey);
        Cache::forget($pendingKey);

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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function pendingPayload(Request $request, array $validated): array
    {
        $pending = $validated;

        foreach (['truck_image', 'driving_license_image', 'car_legal_documents'] as $field) {
            unset($pending[$field]);
        }

        if (UserRole::from($validated['role']) !== UserRole::DRIVER) {
            return $pending;
        }

        $pending['truck_image_path'] = $request->file('truck_image')->store('pending-driver-profiles/trucks', 'public');
        $pending['driving_license_image_path'] = $request->file('driving_license_image')->store('pending-driver-profiles/licenses', 'public');
        $pending['car_legal_documents_path'] = $request->file('car_legal_documents')->store('pending-driver-profiles/documents', 'public');

        return $pending;
    }

    private function sendVerificationOtp(LoginIdentifierType $identifierType, string $identifier, string $channel): string
    {
        $length = $this->authLogin->otpCodeLength();
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $plainCode = (string) random_int((int) $min, (int) $max);

        $purpose = $channel === 'phone' ? OtpPurpose::VerifyPhone : OtpPurpose::VerifyEmail;
        $fingerprint = OtpRepository::fingerprint($identifierType->value, $identifier);

        Cache::put(
            $this->registrationOtpCacheKey($fingerprint),
            [
                'hash' => OtpRepository::hashCode($plainCode),
            ],
            now()->addMinutes($this->authLogin->otpTtlMinutes())
        );

        if ($channel === 'email') {
            Notification::route('mail', $identifier)->notify(new OtpCodeNotification($plainCode, $purpose));
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
     * @param  array<string, mixed>  $pending
     */
    private function createUserFromPendingPayload(array $pending, LoginIdentifierType $identifierType): User
    {
        $role = UserRole::from((string) $pending['role']);

        return DB::transaction(function () use ($pending, $role, $identifierType): User {
            $user = User::query()->create([
                'name' => $pending['name'] ?? null,
                'email' => $pending['email'],
                'phone' => $pending['phone'] ?? null,
                'locale' => $pending['locale'] ?? 'en',
                'password' => $pending['password'] ?? null,
                'role' => $role,
                'email_verified_at' => $identifierType === LoginIdentifierType::Email ? now() : null,
                'phone_verified_at' => $identifierType === LoginIdentifierType::Phone ? now() : null,
            ]);

            if ($role === UserRole::DRIVER) {
                DriverProfile::query()->create([
                    'user_id' => $user->id,
                    'car_brand' => $pending['car_brand'],
                    'car_model' => $pending['car_model'],
                    'car_type' => $pending['car_type'],
                    'license_plate' => $pending['license_plate'],
                    'location' => $pending['location'],
                    'truck_image_path' => $this->movePendingFile((string) $pending['truck_image_path'], 'driver-profiles/trucks'),
                    'driving_license_image_path' => $this->movePendingFile((string) $pending['driving_license_image_path'], 'driver-profiles/licenses'),
                    'car_legal_documents_path' => $this->movePendingFile((string) $pending['car_legal_documents_path'], 'driver-profiles/documents'),
                ]);
            }

            return $user;
        });
    }

    private function movePendingFile(string $pendingPath, string $targetDirectory): string
    {
        $filename = basename($pendingPath);
        $targetPath = $targetDirectory.'/'.$filename;

        Storage::disk('public')->move($pendingPath, $targetPath);

        return $targetPath;
    }

    private function pendingRegistrationCacheKey(LoginIdentifierType $identifierType, string $identifier): string
    {
        return 'registration:pending:'.OtpRepository::fingerprint($identifierType->value, $identifier);
    }

    private function registrationOtpCacheKey(string $fingerprint): string
    {
        return 'registration:otp:'.$fingerprint;
    }
}

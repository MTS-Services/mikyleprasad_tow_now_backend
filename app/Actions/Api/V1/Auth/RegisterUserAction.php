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
use App\Services\Otp\OtpRepository;
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
    ) {}

    /**
     * @return array{user: User, verification_channel: string, expires_in_minutes: int}
     *
     * @throws ValidationException
     */
    public function handle(Request $request): array
    {
        $validated = Validator::make($request->all(), [
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

        $role = UserRole::from($validated['role']);
        $verificationChannel = $this->verificationChannelForRegistration();
        $this->ensureVerificationDeliveryIsAvailable($verificationChannel);

        $user = DB::transaction(function () use ($request, $role, $validated): User {
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

        $user = $user->fresh(['driverProfile']);
        $this->sendVerificationOtp($user, $verificationChannel);

        return [
            'user' => $user,
            'verification_channel' => $verificationChannel,
            'expires_in_minutes' => $this->authLogin->otpTtlMinutes(),
        ];
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
     * @return 'email'|'phone'
     */
    private function sendVerificationOtp(User $user, string $channel): string
    {
        $length = $this->authLogin->otpCodeLength();
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $plainCode = (string) random_int((int) $min, (int) $max);

        $this->otpRepository->put(
            OtpPurpose::VerifyEmail,
            OtpRepository::fingerprint('user', (string) $user->id),
            [
                'user_id' => $user->id,
                'hash' => OtpRepository::hashCode($plainCode),
            ],
            $this->authLogin->otpTtlMinutes()
        );

        $user->notify(new OtpCodeNotification($plainCode, OtpPurpose::VerifyEmail));

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
}

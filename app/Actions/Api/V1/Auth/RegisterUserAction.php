<?php

namespace App\Actions\Api\V1\Auth;

use App\Enums\UserRole;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\Auth\UserLoginHistoryRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $accessToken = $this->issuePersonalAccessTokenAction->handle(
            $user,
            $validated['device_name'] ?? null
        );

        $user = $user->fresh(['driverProfile']);
        $this->userLoginHistoryRecorder->record($user, $request);

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
        ];
    }
}

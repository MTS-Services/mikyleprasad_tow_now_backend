<?php

namespace App\Services;

use App\Models\User;
use App\Models\SiteSetting;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AdminServce
{
    public function __construct() {}

    public function getAdminProfile(Request $request): ?array
    {
        $userId = $request->user()->id;

        $admin = User::query()
            ->whereKey($userId)
            ->where('role', UserRole::ADMIN->value)
            ->with('vehicle')
            ->first();

        if (! $admin) {
            return null;
        }

        $siteSetting = SiteSetting::query()->first();

        return [
            'admin' => $admin,
            'site_setting' => $siteSetting,
        ];
    }

    public function updateAdminProfile(Request $request, array $data): ?array
    {
        Validator::make($data, [
            'name'        => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('users', 'phone')->ignore($request->user()->id),
            ],
            'email' => [
                'sometimes', 'email',
                Rule::unique('users', 'email')->ignore($request->user()->id),
            ],
            'address'     => ['sometimes', 'string', 'max:500'],

            'site_email'  => ['sometimes', 'email'],
            'site_phone'  => ['sometimes', 'string'],
            'site_address' => ['sometimes', 'string'],

            // 'current_password' => ['required_with:password', 'string'],
            // 'password'         => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
            // 'avatar'  => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ])->validate();


        $admin = User::query()
            ->whereKey($request->user()->id)
            ->where('role', UserRole::ADMIN->value)
            ->first();

        if (! $admin) {
            return null;
        }

        // if (! empty($data['password'])) {

        //     if (! Hash::check($data['current_password'], $admin->password)) {
        //         throw ValidationException::withMessages([
        //             'current_password' => ['Current password is incorrect.'],
        //         ]);
        //     }

        //     $admin->update([
        //         'password' => Hash::make($data['password']),
        //     ]);
        // }

        // Handle avatar upload before updating admin profile
        $avatarPath = null;
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $this->deleteAvatarFile($admin->avatar);
            $avatarPath = $this->storeAvatar($data['avatar'], $admin->id);
        }

        // Admin profile update
        $admin->update([
            'name'    => $data['name']    ?? $admin->name,
            'phone'   => $data['phone']   ?? $admin->phone,
            'email'   => $data['email']   ?? $admin->email,
            'address' => $data['address'] ?? $admin->address,
            'avatar'  => $avatarPath ?? $admin->avatar,
        ]);

        // Site setting update
        $siteSetting = SiteSetting::query()->first();

        if ($siteSetting) {
            $siteSetting->update([
                'site_email'   => $data['site_email']   ?? $siteSetting->site_email,
                'site_phone'   => $data['site_phone']   ?? $siteSetting->site_phone,
                'site_address' => $data['site_address'] ?? $siteSetting->site_address,
            ]);
        }

        
        return [
            'admin'        => $admin->fresh(),
            'site_setting' => $siteSetting?->fresh(),
        ];
    }

    private function storeAvatar(UploadedFile $file, int|string $adminId): string
    {
        $path = $file->store("avatars/{$adminId}", 'public');

        return Storage::url($path);
    }

    private function deleteAvatarFile(?string $avatarUrl): void
    {
        if (! $avatarUrl) {
            return;
        }

        $path = ltrim(str_replace('/storage', '', $avatarUrl), '/');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}

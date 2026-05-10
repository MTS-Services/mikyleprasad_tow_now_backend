<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserServce
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }



    public function updateProfile(Request $request, array $data): ?array
    {
        $user = $request->user();

        Validator::make($data, [
            'name'         => ['sometimes', 'string', 'max:255'],
            'phone'        => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'email'        => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'address'      => ['sometimes', 'string', 'max:500'],
            'avatar'       => ['sometimes', 'image', 'max:2048'],

            'site_email'   => ['sometimes', 'email'],
            'site_phone'   => ['sometimes', 'string'],
            'site_address' => ['sometimes', 'string'],
        ])->validate();

        $foundUser = User::query()->whereKey($user->id)->first();

        if (! $foundUser) {
            return null;
        }

        $avatarPath = null;
        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $this->deleteAvatarFile($foundUser->avatar);
            $avatarPath = $this->storeAvatar($data['avatar'], $foundUser->id);
        }

        $foundUser->update([
            'name'    => $data['name']    ?? $foundUser->name,
            'phone'   => $data['phone']   ?? $foundUser->phone,
            'email'   => $data['email']   ?? $foundUser->email,
            'address' => $data['address'] ?? $foundUser->address,
            'avatar'  => $avatarPath      ?? $foundUser->avatar,
        ]);
        $siteFields = ['site_email', 'site_phone', 'site_address'];
        $hasSiteData = collect($siteFields)->contains(fn($field) => array_key_exists($field, $data));

        if ($hasSiteData) {
            $this->updateSiteSetting($data);
        }

        return ['user' => $foundUser->fresh()];
    }

    // -------------------- Private Methods --------------------

    private function updateSiteSetting(array $data): void
    {
        $siteSetting = SiteSetting::query()->first();

        if (! $siteSetting) {
            return;
        }

        $siteSetting->update([
            'site_email'   => $data['site_email']   ?? $siteSetting->site_email,
            'site_phone'   => $data['site_phone']   ?? $siteSetting->site_phone,
            'site_address' => $data['site_address'] ?? $siteSetting->site_address,
        ]);
    }

    private function storeAvatar(UploadedFile $file, int|string $userId): string
    {
        return $file->store("avatars/{$userId}", 'public');
    }

    private function deleteAvatarFile(?string $avatarPath): void
    {
        if (! $avatarPath) {
            return;
        }

        if (Storage::disk('public')->exists($avatarPath)) {
            Storage::disk('public')->delete($avatarPath);
        }
    }
}

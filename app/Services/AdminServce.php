<?php

namespace App\Services;

use App\Models\User;
use App\Models\SiteSetting;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'phone'       => ['sometimes', 'string', 'max:20'],
            'address'     => ['sometimes', 'string', 'max:500'],

            'site_email'  => ['sometimes', 'email'],
            'site_phone'  => ['sometimes', 'string'],
            'site_address' => ['sometimes', 'string'],
        ])->validate();

        $admin = User::query()
            ->whereKey($request->user()->id)
            ->where('role', UserRole::ADMIN->value)
            ->first();

        if (! $admin) {
            return null;
        }

        // admin update
        $admin->update([
            'name'    => $data['name'] ?? $admin->name,
            'phone'   => $data['phone'] ?? $admin->phone,
            'address' => $data['address'] ?? $admin->address,
        ]);

        // site setting update
        $siteSetting = SiteSetting::query()->first();

        if ($siteSetting) {
            $siteSetting->update([
                'site_email' => $data['site_email'] ?? $siteSetting->site_email,
                'site_phone' => $data['site_phone'] ?? $siteSetting->site_phone,
                'site_address' => $data['site_address'] ?? $siteSetting->site_address,
            ]);
        }

        return [
            'admin' => $admin->fresh(),
            'site_setting' => $siteSetting?->fresh(),
        ];
    }
}

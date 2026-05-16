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

    public function updateVehicle(Request $request, array $data)
    {
        $driver = User::query()
            ->whereKey($request->user()->id)
            ->first();

        if (! $driver) {
            return null;
        }

        if (! $driver->vehicle) {
            return null;
        }

        $truckImagePath = null;
        if (isset($data['truck_image']) && $data['truck_image'] instanceof UploadedFile) {
            $this->deleteVehicleFile($driver->vehicle->truck_image);
            $truckImagePath = $this->storeVehicleFile($data['truck_image'], $driver->id, 'truck_images');
        }

        $drivingLicensePath = null;
        if (isset($data['driving_license_image']) && $data['driving_license_image'] instanceof UploadedFile) {
            $this->deleteVehicleFile($driver->vehicle->driving_license_image);
            $drivingLicensePath = $this->storeVehicleFile($data['driving_license_image'], $driver->id, 'driving_licenses');
        }

        $legalDocumentsPath = null;
        if (isset($data['legal_documents']) && $data['legal_documents'] instanceof UploadedFile) {
            $this->deleteVehicleFile($driver->vehicle->legal_documents);
            $legalDocumentsPath = $this->storeVehicleFile($data['legal_documents'], $driver->id, 'legal_documents');
        }

        $driver->vehicle->update([
            'name'                  => $data['name']           ?? $driver->vehicle->name,
            'model'                 => $data['model']          ?? $driver->vehicle->model,
            'brand'                 => $data['brand']          ?? $driver->vehicle->brand,
            'insurance_status'      => $data['insurance_status'] ?? $driver->vehicle->insurance_status,
            'license_plate'         => $data['license_plate']  ?? $driver->vehicle->license_plate,
            'capacity'              => $data['capacity']       ?? $driver->vehicle->capacity,
            'truck_image'           => $truckImagePath         ?? $driver->vehicle->truck_image,
            'driving_license_image' => $drivingLicensePath     ?? $driver->vehicle->driving_license_image,
            'legal_documents'       => $legalDocumentsPath     ?? $driver->vehicle->legal_documents,
        ]);

        return ['user' => $driver->fresh()];
    }


    private function storeVehicleFile(UploadedFile $file, int|string $userId, string $folder): string
    {
        return $file->store("vehicles/{$userId}/{$folder}", 'public');
    }

    private function deleteVehicleFile(?string $filePath): void
    {
        if (! $filePath) {
            return;
        }

        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }
    }
}

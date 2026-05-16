<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Filters\UserActorFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerServce
{
    public function __construct(
        private readonly UserActorFilters $userActorFilters
    ) {}

    /**
     * @param  array{q?: ?string, status?: ?string, featured?: string, per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', UserRole::USER->value)
            ->orderByDesc('id');

        $this->userActorFilters->apply($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?User
    {
        return User::query()
            ->whereKey($id)
            ->where('role', UserRole::USER->value)
            ->with('requestedRides.driver', 'requestedRides.review')
            ->first();
    }

    public function getCustomerProfile(): ?User
    {
        return User::query()
            ->whereKey(auth()->id())
            ->where('role', UserRole::USER->value)
            ->first();
    }

    public function updateCustomerProfile(Request $request, array $data): ?User
    {
        Validator::make($data, [
            'name'   => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('users', 'phone')->ignore($request->user()->id),
            ],
            'email' => [
                'sometimes', 'email',
                Rule::unique('users', 'email')->ignore($request->user()->id),
            ],
            'avatar' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ])->validate();

        $customer = $this->getCustomerProfile();

        if (! $customer) {
            return null;
        }

        if (isset($data['avatar']) && $data['avatar'] instanceof UploadedFile) {
            $this->deleteAvatarFile($customer->avatar);
            $data['avatar'] = $this->storeAvatar($data['avatar'], $customer->id);
        }

        $customer->update($data);

        return $customer->fresh();
    }

    private function storeAvatar(UploadedFile $file, int|string $userId): string
    {
        $path = $file->store("avatars/{$userId}", 'public');

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

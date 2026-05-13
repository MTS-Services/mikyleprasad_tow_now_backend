<?php

namespace App\services;

use App\Enums\UserRole;
use App\Models\ContactQuery;
use App\Services\UserNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContactQueryService
{
    /**
     * Create a new class instance.
     */
    public function __construct(
         private readonly UserNotificationService $userNotificationService,
        private readonly ContactQuery $contactQuery
    )
    {}

    public function getAll()
    {
        return $this->contactQuery->all();
    }

    /**
     * @param  array{per_page?: int}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = ContactQuery::query()
            ->select([
                'id',
                'name',
                'email',
                'subject',
                'message',
                'created_at',
                'updated_at'
            ])
            ->orderByDesc('id');

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage)->withQueryString();
    }

    public function get($id)
    {
        return $this->contactQuery->find($id);
    }

    public function create(array $data)
    {
         $contactQuery = $this->contactQuery->create($data);
         $this->notifyAdminsContactQuerySubmitted($contactQuery);
       return $contactQuery;
    }

    private function notifyAdminsContactQuerySubmitted(ContactQuery $contactQuery): void
    {
        $this->userNotificationService->notifyUsersByRole(
            UserRole::ADMIN,
            "New contact query submitted",
            "Contact subject '{$contactQuery->subject}' has been submitted.",
        );
    }
}

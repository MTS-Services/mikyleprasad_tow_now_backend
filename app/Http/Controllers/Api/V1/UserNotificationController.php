<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserNotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserNotificationController extends Controller
{
    public function __construct(
        private readonly UserNotificationService $userNotificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->user()->userNotifications()->with('sender');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $perPage = $validated['per_page'] ?? 15;

        $paginator = $query->paginate($perPage)->withQueryString();

        return sendResponse(
            status: true,
            message: __('api.notifications_fetched_successfully'),
            data: UserNotificationResource::collection($paginator),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->with('sender')
            ->whereKey($id)
            ->first();

        if (! $notification) {
            return sendResponse(
                status: false,
                message: __('api.notification_not_found'),
                statusCode: HttpStatus::HTTP_NOT_FOUND
            );
        }

        $this->userNotificationService->markAsRead($notification);
        $notification->refresh();
        $notification->load('sender');

        return sendResponse(
            status: true,
            message: __('api.notification_marked_read'),
            data: new UserNotificationResource($notification),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->first();

        if (! $notification) {
            return sendResponse(
                status: false,
                message: __('api.notification_not_found'),
                statusCode: HttpStatus::HTTP_NOT_FOUND
            );
        }

        $this->userNotificationService->dismiss($notification);

        return sendResponse(
            status: true,
            message: __('api.notification_dismissed'),
            data: null,
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->userNotificationService->markAllAsRead($request->user());

        return sendResponse(
            status: true,
            message: __('api.notifications_marked_all_read'),
            data: ['updated' => $count],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    /**
     * Local / testing only: create a notification for the current user and broadcast it (verify in Pusher Debug Console).
     */
    public function storeTest(Request $request): JsonResponse
    {
        abort_unless($this->testNotificationRouteEnabled(), HttpStatus::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'max:120'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'data' => ['sometimes', 'array'],
            'action_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'origin' => ['sometimes', 'string', Rule::in(['system', 'self'])],
        ]);

        /** @var User $recipient */
        $recipient = $request->user();
        $origin = $validated['origin'] ?? 'system';
        $sender = $origin === 'self' ? $recipient : null;

        $notification = $this->userNotificationService->notify(
            $recipient,
            $validated['type'] ?? 'test.pusher',
            $validated['title'] ?? __('api.notification_test_default_title'),
            $validated['body'] ?? __('api.notification_test_default_body'),
            $validated['data'] ?? ['source' => 'api_test_route'],
            $validated['action_url'] ?? null,
            $sender,
        );

        $notification->load('sender');

        return sendResponse(
            status: true,
            message: __('api.notification_test_created'),
            data: new UserNotificationResource($notification),
            statusCode: HttpStatus::HTTP_CREATED,
            additional: [
                'broadcast_hint' => __('api.notification_test_broadcast_hint'),
            ]
        );
    }

    private function testNotificationRouteEnabled(): bool
    {
        if (app()->isLocal() || app()->environment('testing') || app()->environment('local')) {
            return true;
        }

        return filter_var(env('NOTIFICATIONS_ALLOW_TEST_BROADCAST_ROUTE', false), FILTER_VALIDATE_BOOLEAN);
    }
}

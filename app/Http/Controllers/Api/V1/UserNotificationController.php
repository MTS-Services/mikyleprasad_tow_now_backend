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
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        try {
            $query = $request->user()->userNotifications()->with('sender');

            if ($request->boolean('unread')) {
                $query->whereNull('read_at');
            }

            $perPage = $validated['per_page'] ?? 10;

            $paginator = $query->paginate($perPage)->withQueryString();

            return sendResponse(
                status: true,
                message: __('api.notifications_fetched_successfully'),
                data: UserNotificationResource::collection($paginator),
                statusCode: HttpStatus::HTTP_OK
            );
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                data: null,
                statusCode: $e->getStatusCode()
            );
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $notification = $this->findOwnedNotification($request->user(), $id);

        if ($notification === null) {
            return sendResponse(
                status: false,
                message: __('api.notification_not_found'),
                data: null,
                statusCode: HttpStatus::HTTP_NOT_FOUND
            );
        }

        $this->userNotificationService->markAsRead($notification);
        $notification->refresh();
        $notification->load('sender');

        return sendResponse(
            status: true,
            message: __('api.notification_fetched_successfully'),
            data: new UserNotificationResource($notification),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->findOwnedNotification($request->user(), $id);

        if (! $notification) {
            return sendResponse(
                status: false,
                message: __('api.notification_not_found'),
                data: null,
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

    public function markAsUnread(Request $request, int $id): JsonResponse
    {
        $notification = $this->findOwnedNotification($request->user(), $id);

        if ($notification === null) {
            return sendResponse(
                status: false,
                message: __('api.notification_not_found'),
                data: null,
                statusCode: HttpStatus::HTTP_NOT_FOUND
            );
        }

        $this->userNotificationService->markAsUnread($notification);
        $notification->refresh();
        $notification->load('sender');

        return sendResponse(
            status: true,
            message: __('api.notification_marked_unread'),
            data: new UserNotificationResource($notification),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        try {
            $count = $this->userNotificationService->markAllAsRead($request->user());

            return sendResponse(
                status: true,
                message: __('api.notifications_marked_all_read'),
                data: ['updated' => $count],
                statusCode: HttpStatus::HTTP_OK
            );
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                data: null,
                statusCode: $e->getStatusCode()
            );
        }
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
        try {
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
        } catch (HttpException $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                data: null,
                statusCode: $e->getStatusCode()
            );
        }
    }

    /**
     * Local / testing only: send a raw FCM data+notification message to an arbitrary device token (via server SDK).
     */
    public function sendTestPushToToken(Request $request): JsonResponse
    {
        abort_unless($this->testNotificationRouteEnabled(), HttpStatus::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'min:10', 'max:4096'],
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string', 'max:2000'],
        ]);

        $token = trim($validated['fcm_token']);
        $title = $validated['title'] ?? __('api.notification_test_push_default_title');
        $body = $validated['body'] ?? __('api.notification_test_push_default_body');

        $stringData = [
            'source' => 'api_test_push_token',
            'initiated_by_user_id' => (string) $request->user()->id,
        ];

        try {
            $messaging = Firebase::messaging();

            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification(Notification::create($title, $body))
                ->withData($stringData);

            $sendResult = $messaging->send($message);

            return sendResponse(
                status: true,
                message: __('api.notification_test_push_sent'),
                data: ['fcm_message_id' => fcm_send_result_message_id($sendResult)],
                statusCode: HttpStatus::HTTP_OK
            );
        } catch (InvalidMessage $e) {
            return sendResponse(
                status: false,
                message: $e->getMessage(),
                data: ['error' => 'invalid_message'],
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Throwable $e) {
            report($e);

            return sendResponse(
                status: false,
                message: __('api.notification_test_push_failed'),
                data: ['exception' => $e::class],
                statusCode: HttpStatus::HTTP_BAD_GATEWAY
            );
        }
    }

    private function testNotificationRouteEnabled(): bool
    {
        if (app()->isLocal() || app()->environment('testing') || app()->environment('local')) {
            return true;
        }

        return filter_var(env('NOTIFICATIONS_ALLOW_TEST_BROADCAST_ROUTE', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function findOwnedNotification(User $user, int $id): ?UserNotification
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->with('sender')
            ->first();
    }
}

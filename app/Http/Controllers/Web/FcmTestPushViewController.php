<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Kreait\Firebase\Exception\InvalidArgumentException as FirebaseInvalidArgumentException;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class FcmTestPushViewController extends Controller
{
    public function create(): View
    {
        abort_unless($this->routeEnabled(), HttpStatus::HTTP_NOT_FOUND);

        $users = User::query()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->orderBy('name')
            ->orderBy('email')
            ->get(['id', 'name', 'email']);

        return view('dev.fcm-test-push', [
            'users' => $users,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->routeEnabled(), HttpStatus::HTTP_NOT_FOUND);

        /** @var list<int> $allowedIds */
        $allowedIds = User::query()
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->pluck('id')
            ->all();

        $userIdRaw = $request->input('user_id');
        $userId = null;
        if ($userIdRaw !== null && $userIdRaw !== '') {
            $userId = (int) $userIdRaw;
        }

        $validator = Validator::make(
            array_merge($request->all(), ['user_id' => $userId]),
            [
                'user_id' => ['nullable', 'integer', Rule::in($allowedIds)],
                'fcm_token' => ['nullable', 'string', 'min:10', 'max:4096'],
                'title' => ['nullable', 'string', 'max:255'],
                'body' => ['nullable', 'string', 'max:2000'],
            ]
        );

        $validator->after(function ($v) use ($userId): void {
            if ($userId === null || $userId === 0) {
                $token = trim((string) $v->getValue('fcm_token'));
                if ($token === '') {
                    $v->errors()->add('recipient', 'Select a user with an FCM token, or enter a device token manually.');
                }
            }
        });

        $validated = $validator->validate();

        $title = $validated['title'] ?? __('api.notification_test_push_default_title');
        $body = $validated['body'] ?? __('api.notification_test_push_default_body');

        $token = '';
        if (! empty($validated['user_id'])) {
            $user = User::query()->findOrFail((int) $validated['user_id']);
            $token = trim((string) $user->fcm_token);
            if ($token === '') {
                return back()->withInput()->withErrors(['user_id' => 'Selected user has no FCM token.']);
            }
        } else {
            $token = trim((string) ($validated['fcm_token'] ?? ''));
        }

        $stringData = [
            'source' => 'web_dev_fcm_test_push',
        ];

        try {
            $messaging = Firebase::messaging();
            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification(Notification::create($title, $body))
                ->withData($stringData);

            $sendResult = $messaging->send($message);

            return back()->with('status', 'Sent. FCM message id: '.fcm_send_result_message_id($sendResult));
        } catch (InvalidMessage $e) {
            return back()->withInput()->withErrors(['fcm' => $e->getMessage()]);
        } catch (FirebaseInvalidArgumentException $e) {
            report($e);

            return back()->withInput()->withErrors(['fcm' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['fcm' => __('api.notification_test_push_failed').' ('.$e::class.')']);
        }
    }

    private function routeEnabled(): bool
    {
        if (app()->isLocal() || app()->environment('testing') || app()->environment('local')) {
            return true;
        }

        return filter_var(env('NOTIFICATIONS_ALLOW_TEST_BROADCAST_ROUTE', false), FILTER_VALIDATE_BOOLEAN);
    }
}

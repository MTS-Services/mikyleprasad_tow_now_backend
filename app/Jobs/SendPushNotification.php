<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FcmNotificationLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;

class SendPushNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** FCM failures are logged and not rethrown; retries mainly help transient network issues. */
    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public User $recipient,
        public string $title,
        public string $body,
        public array $data = []
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $token = $this->recipient->fcm_token;
        if ($token === null || $token === '') {
            return;
        }

        /** @var FcmNotificationLog $log */
        $log = FcmNotificationLog::query()->create([
            'user_id' => $this->recipient->id,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'status' => 'pending',
        ]);

        $stringData = [];
        foreach ($this->data as $key => $value) {
            $stringData[(string) $key] = $value === null ? '' : (is_scalar($value) ? (string) $value : json_encode($value));
        }

        try {
            $messaging = Firebase::messaging();

            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification(Notification::create($this->title, $this->body))
                ->withData($stringData);

            $sendResult = $messaging->send($message);

            $log->forceFill([
                'status' => 'sent',
                'fcm_message_id' => fcm_send_result_message_id($sendResult),
                'sent_at' => now(),
            ])->save();
        } catch (InvalidMessage $e) {
            $this->recipient->forceFill(['fcm_token' => null])->save();
            $log->forceFill(['status' => 'failed'])->save();
            Log::warning('fcm.invalid_message', [
                'fcm_notification_log_id' => $log->id,
                'recipient_id' => $this->recipient->id,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $log->forceFill(['status' => 'failed'])->save();
            Log::error('fcm.send_failed', [
                'fcm_notification_log_id' => $log->id,
                'recipient_id' => $this->recipient->id,
                'title' => $this->title,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            report($e);
        }
    }
}

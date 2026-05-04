<?php

namespace App\Notifications\Auth;

use App\Enums\OtpPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $plainCode,
        protected OtpPurpose $purpose
    ) {}

    /**
     * @var array<int, int>
     */
    public $backoff = [10, 30, 60];

    public int $tries = 5;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name');
        $meta = $this->purposeMeta($appName);

        return (new MailMessage)
            ->subject($meta['subject'])
            ->view('mail.otp-modern', [
                'subjectLine' => $meta['subject'],
                'preheader' => $meta['preheader'],
                'title' => $meta['title'],
                'intro' => $meta['intro'],
                'code' => $this->plainCode,
                'expiryMinutes' => $meta['expiryMinutes'],
                'appName' => $appName,
            ]);
    }

    /**
     * @return array{subject: string, preheader: string, title: string, intro: string, expiryMinutes: int}
     */
    private function purposeMeta(string $appName): array
    {
        $purposeKey = match ($this->purpose) {
            OtpPurpose::Login => 'login',
            OtpPurpose::VerifyEmail => 'verify_email',
            OtpPurpose::VerifyPhone => 'verify_phone',
            OtpPurpose::SensitiveAction => 'sensitive_action',
            OtpPurpose::PasswordReset => 'password_reset',
        };

        $expiryMinutes = match ($this->purpose) {
            OtpPurpose::PasswordReset => max(1, (int) config('account.password_reset_otp_ttl_minutes', 15)),
            default => max(1, (int) config('auth_login.otp_code_ttl_minutes', 10)),
        };

        return [
            'subject' => __("mail.otp_modern.{$purposeKey}.subject", ['app' => $appName]),
            'preheader' => __("mail.otp_modern.{$purposeKey}.preheader", ['app' => $appName]),
            'title' => __("mail.otp_modern.{$purposeKey}.title", ['app' => $appName]),
            'intro' => __("mail.otp_modern.{$purposeKey}.intro", ['app' => $appName]),
            'expiryMinutes' => $expiryMinutes,
        ];
    }
}

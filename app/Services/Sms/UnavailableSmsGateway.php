<?php

namespace App\Services\Sms;

use App\Contracts\Sms\SmsGateway;

class UnavailableSmsGateway implements SmsGateway
{
    public function send(string $e164Phone, string $message): bool
    {
        return false;
    }
}

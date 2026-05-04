<?php

namespace App\Contracts\Sms;

interface SmsGateway
{
    /**
     * @return true when the message was accepted for delivery
     */
    public function send(string $e164Phone, string $message): bool;
}

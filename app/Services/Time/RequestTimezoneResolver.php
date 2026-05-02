<?php

namespace App\Services\Time;

use DateTimeZone;
use Illuminate\Http\Request;

final class RequestTimezoneResolver
{
    public function __construct(private readonly Request $request) {}

    public function resolve(): string
    {
        if (($tz = $this->fromHeader()) !== null) {
            return $tz;
        }

        if (($tz = $this->fromAcceptTimezone()) !== null) {
            return $tz;
        }

        if (($tz = $this->fromUser()) !== null) {
            return $tz;
        }

        return (string) config('app.timezone', 'UTC');
    }

    private function fromHeader(): ?string
    {
        if (! config('timezone.timezone_override_enabled', true)) {
            return null;
        }

        $headerName = (string) config('timezone.timezone_override_header', 'X-App-Timezone');
        $raw = $this->request->header($headerName);

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $candidate = trim($raw);

        return $this->isValidTimezone($candidate) ? $candidate : null;
    }

    private function fromAcceptTimezone(): ?string
    {
        if (! config('timezone.timezone_override_enabled', true)) {
            return null;
        }

        $headerName = (string) config('timezone.accept_timezone_header', 'Accept-Timezone');
        $raw = $this->request->header($headerName);

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $candidate = trim($raw);

        return $this->isValidTimezone($candidate) ? $candidate : null;
    }

    private function fromUser(): ?string
    {
        $user = $this->request->user();
        if ($user === null) {
            return null;
        }

        $candidate = trim((string) ($user->timezone ?? ''));
        if ($candidate === '') {
            return null;
        }

        return $this->isValidTimezone($candidate) ? $candidate : null;
    }

    private function isValidTimezone(string $candidate): bool
    {
        return in_array($candidate, DateTimeZone::listIdentifiers(), true);
    }
}

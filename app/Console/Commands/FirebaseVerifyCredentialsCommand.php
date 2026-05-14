<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

use function str_contains;

/**
 * Helps debug OAuth `invalid_grant` by validating the service account JSON and fetching an access token.
 *
 * This is Google's server-to-server OAuth for the Admin SDK — not user "Sign in with Google" or any
 * OAuth client you configure for your app's end users.
 */
class FirebaseVerifyCredentialsCommand extends Command
{
    protected $signature = 'firebase:verify-credentials';

    protected $description = 'Validate the Firebase service account JSON (server-to-Google auth, not user login)';

    public function handle(): int
    {
        $this->comment(
            'Firebase Admin uses a service account JSON: your server proves identity to Google with that file\'s private key. '
            .'You do not need Google Sign-In, OAuth consent screen, or a "third party app" in your product for FCM from Laravel.'
        );
        $this->newLine();

        $project = (string) config('firebase.default');
        $raw = config('firebase.projects.'.$project.'.credentials');

        if ($raw === null || $raw === '') {
            $this->error('No credentials path configured. Set FIREBASE_CREDENTIALS in .env to your service account JSON file.');

            return self::FAILURE;
        }

        if (is_array($raw)) {
            $this->warn('Credentials are configured as an array in config. This command only validates file-based JSON paths.');

            return self::SUCCESS;
        }

        $path = $this->resolveCredentialsPath((string) $raw);
        if (! is_file($path)) {
            $this->error("Credentials file not found: {$path}");

            return self::FAILURE;
        }

        $this->line('Resolved path: '.$path);

        try {
            $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Invalid JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        if (($json['type'] ?? '') !== 'service_account') {
            $this->error(
                'This file is not a Firebase/Google **service account** key (expected "type": "service_account"). '
                .'Application Default Credentials / `gcloud auth application-default login` JSON uses "authorized_user" and will cause **invalid_grant** here. '
                .'Download a new key from Firebase Console → Project settings → Service accounts → Generate new private key.'
            );

            return self::FAILURE;
        }

        $jsonProjectId = (string) ($json['project_id'] ?? '');
        if ($jsonProjectId !== '' && $jsonProjectId !== $project) {
            $this->warn("FIREBASE_PROJECT ({$project}) differs from project_id in JSON ({$jsonProjectId}). Align them to avoid subtle auth issues.");
        }

        $this->info('client_email: '.($json['client_email'] ?? '(missing)'));
        $this->info('project_id (in JSON): '.($jsonProjectId !== '' ? $jsonProjectId : '(missing)'));

        try {
            $creds = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $path
            );
            $handler = HttpHandlerFactory::build();
            $token = $creds->fetchAuthToken($handler);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $this->error('OAuth token request failed: '.$msg);
            if (str_contains($msg, 'Invalid JWT Signature')) {
                $this->newLine();
                $this->warn('Google rejected the JWT signature for this service account file.');
                $this->line(' • Download a **new** key: Firebase Console → Project settings → Service accounts → Generate new private key.');
                $this->line(' • Replace `storage/app/firebase-credentials.json` (or your FIREBASE_CREDENTIALS path) — do not hand-edit the `private_key`.');
                $this->line(' • Ensure the file is UTF-8 without BOM and was not truncated by email/chat.');
            } elseif (str_contains($msg, 'invalid_grant')) {
                $this->newLine();
                $this->line('Common causes of <fg=red>invalid_grant</>:');
                $this->line(' • Key was rotated/revoked in Google Cloud IAM — generate a new JSON key.');
                $this->line(' • private_key corrupted — use the downloaded JSON file path, not pasted JSON in .env.');
                $this->line(' • Wrong credential type — must be service account JSON (`"type":"service_account"`), not OAuth "authorized_user".');
                $this->line(' • System clock skew — sync Windows time (NTP).');
            }

            return self::FAILURE;
        }

        if (isset($token['error'])) {
            $this->error('Token response error: '.($token['error'] ?? '').' — '.($token['error_description'] ?? ''));

            return self::FAILURE;
        }

        if (empty($token['access_token'])) {
            $this->error('Unexpected token response (no access_token).');

            return self::FAILURE;
        }

        $this->info('OK — received access token (Firebase Messaging scope). Kreait should be able to send FCM from this machine.');

        return self::SUCCESS;
    }

    private function resolveCredentialsPath(string $credentials): string
    {
        $isJsonString = str_starts_with($credentials, '{');
        $isAbsoluteLinuxPath = str_starts_with($credentials, '/');
        $isAbsoluteWindowsPath = str_contains($credentials, ':\\');
        $isRelativePath = ! $isJsonString && ! $isAbsoluteLinuxPath && ! $isAbsoluteWindowsPath;

        return $isRelativePath ? base_path($credentials) : $credentials;
    }
}

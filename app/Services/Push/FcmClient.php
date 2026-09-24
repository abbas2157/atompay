<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 API, with no SDK: a service-account JWT
 * is exchanged for an OAuth access token (cached ~55 min), then one POST
 * per device. Disabled - every send is a no-op - until
 * config('services.fcm.credentials') points at a service-account JSON.
 */
class FcmClient
{
    public const SENT = 'sent';
    public const INVALID_TOKEN = 'invalid_token';
    public const FAILED = 'failed';
    public const DISABLED = 'disabled';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?array $credentials = null;

    public function enabled(): bool
    {
        $path = config('services.fcm.credentials');

        return is_string($path) && $path !== '' && is_readable($path);
    }

    /**
     * @param array<string, scalar|null> $data deep-link payload; FCM requires string values
     * @return string one of the class constants
     */
    public function send(string $token, string $title, string $body, array $data = []): string
    {
        if (! $this->enabled()) {
            return self::DISABLED;
        }

        $response = Http::withToken($this->accessToken())
            ->timeout(10)
            ->post("https://fcm.googleapis.com/v1/projects/{$this->credentials()['project_id']}/messages:send", [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map(fn ($v) => (string) $v, array_filter($data, fn ($v) => $v !== null)),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'atompay_default']],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ]);

        if ($response->successful()) {
            return self::SENT;
        }

        // The app was uninstalled or the token rotated: stop sending to it.
        $status = $response->json('error.details.0.errorCode') ?? $response->json('error.status');
        if ($response->status() === 404 || in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            return self::INVALID_TOKEN;
        }

        Log::warning('FCM send failed', ['status' => $response->status(), 'error' => $response->json('error.message')]);

        return self::FAILED;
    }

    private function accessToken(): string
    {
        return Cache::remember('atompay.fcm.access_token', 55 * 60, function () {
            $creds = $this->credentials();
            $now = time();

            $jwt = $this->jwt([
                'iss' => $creds['client_email'],
                'scope' => self::SCOPE,
                'aud' => $creds['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $creds['private_key']);

            $response = Http::asForm()->timeout(10)->post($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->throw()->json('access_token')
                ?? throw new RuntimeException('FCM: no access_token in OAuth response.');
        });
    }

    private function jwt(array $claims, string $privateKey): string
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM: could not sign the service-account JWT.');
        }

        return $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri?: string} */
    private function credentials(): array
    {
        return $this->credentials ??= json_decode((string) file_get_contents(config('services.fcm.credentials')), true)
            ?: throw new RuntimeException('FCM: the service-account file is not valid JSON.');
    }
}

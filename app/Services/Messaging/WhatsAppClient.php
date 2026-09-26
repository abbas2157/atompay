<?php

namespace App\Services\Messaging;

use App\Support\Pakistan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cloud API on AtomShop's business number - used ONLY for
 * password-reset codes. Sends the approved `auth_otp` template with exactly
 * the payload AtomShop's WhatsAppTrait::send_otp() sends: the code in the
 * body and again as the copy-code button parameter.
 *
 * Without credentials outside production the code is logged instead, so the
 * flow can be tested locally without a real phone.
 */
class WhatsAppClient
{
    public function configured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /** Whether a code can be delivered at all (for real, or to the log locally). */
    public function available(): bool
    {
        return $this->configured() || ! app()->isProduction();
    }

    /** @param string $mobile any Pakistani mobile format */
    public function sendOtp(string $mobile, string $code): bool
    {
        $local = Pakistan::normalizeMobile($mobile);
        if ($local === '') {
            return false;
        }

        if (! $this->configured()) {
            if (app()->isProduction()) {
                return false;
            }
            Log::info('WhatsApp not configured - password reset code (local only)', ['to' => $local, 'code' => $code]);

            return true;
        }

        $config = config('services.whatsapp');

        $response = Http::withToken($config['token'])
            ->timeout(15)
            ->post("https://graph.facebook.com/{$config['api_version']}/{$config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => '92'.substr($local, 1),          // 03001234567 -> 923001234567
                'type' => 'template',
                'template' => [
                    'name' => $config['otp_template'],
                    'language' => ['code' => $config['otp_language']],
                    'components' => [
                        ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                        ['type' => 'button', 'sub_type' => 'url', 'index' => 0, 'parameters' => [['type' => 'text', 'text' => $code]]],
                    ],
                ],
            ]);

        if ($response->successful() && $response->json('messages.0.id')) {
            return true;
        }

        Log::warning('WhatsApp OTP send failed', [
            'status' => $response->status(),
            'error' => $response->json('error.message'),
            'code' => $response->json('error.code'),
        ]);

        return false;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enums\EmploymentStatus;
use App\Models\Enums\IncomeSource;
use App\Services\Messaging\WhatsAppClient;
use App\Services\Push\FcmClient;
use Illuminate\Http\JsonResponse;

/** Public, cacheable facts the app needs before (or without) signing in. */
class MetaController extends Controller
{
    /** Called on every launch: force-update check, links, support contacts. */
    public function appConfig(FcmClient $fcm, WhatsAppClient $whatsapp): JsonResponse
    {
        $api = config('atompay.api');

        return response()->json(['data' => [
            'min_version' => $api['min_version'],
            'store_url' => $api['store_url'],
            'shop_url' => config('atompay.shop_url'),
            // The website's own forgot-password page; the app uses /auth/password/* natively.
            'password_reset_url' => route('password.request'),
            'support' => $api['support'],
            'features' => [
                'push' => $fcm->enabled(),
                // Which ways a reset code can be sent: email always, WhatsApp when configured.
                'password_reset_channels' => $whatsapp->available() ? ['email', 'whatsapp'] : ['email'],
            ],
        ]]);
    }

    /** Values and labels for the application form's pickers. */
    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'employment_statuses' => array_map(fn (EmploymentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'has_employer' => $s->hasEmployer(),   // employer_name is required when true
            ], EmploymentStatus::cases()),
            'income_sources' => array_map(fn (IncomeSource $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], IncomeSource::cases()),
        ]]);
    }
}

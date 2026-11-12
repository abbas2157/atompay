<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enums\EmploymentStatus;
use App\Models\Enums\IncomeSource;
use App\Services\CompanySettings;
use App\Services\InAppOrderService;
use App\Services\Messaging\WhatsAppClient;
use App\Services\Push\FcmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/** Public, cacheable facts the app needs before (or without) signing in. */
class MetaController extends Controller
{
    /** Called on every launch: force-update check, links, support contacts. */
    public function appConfig(FcmClient $fcm, WhatsAppClient $whatsapp, CompanySettings $company): JsonResponse
    {
        $api = config('atompay.api');

        return response()->json(['data' => [
            'min_version' => $api['min_version'],
            'store_url' => $api['store_url'],
            'shop_url' => config('atompay.shop_url'),
            // The website's own forgot-password page; the app uses /auth/password/* natively.
            'password_reset_url' => route('password.request'),
            'privacy_url' => $api['links']['privacy_url'] ?: route('privacy'),
            'terms_url' => $api['links']['terms_url'] ?: null,
            'account_deletion_url' => $api['links']['account_deletion_url'] ?: route('account.delete.info'),
            // Each contact is null (not "") when unset, so the app hides that row.
            'support' => array_map(fn ($value) => blank($value) ? null : trim((string) $value), [
                'phone' => $api['support']['phone'] ?? null,
                'whatsapp' => $api['support']['whatsapp'] ?? null,
                'email' => $api['support']['email'] ?? null,
            ]),
            // Company facts from Atomshop's admin panel (.env as fallback); every key always present, null when unset.
            'business' => $company->all(),
            'maintenance' => [
                'active' => (bool) $api['maintenance']['active'],
                'message' => $api['maintenance']['message'] ?: null,
                'until' => $this->isoOrNull($api['maintenance']['until']),
            ],
            // The ecosystem screen. A null link means "not published on that store yet".
            'apps' => $this->apps(),
            'features' => [
                'push' => $fcm->enabled(),
                'voice' => (bool) $api['voice'],
                // Which ways a reset code can be sent: email always, WhatsApp when configured.
                // Where codes can go (sign-up and reset): email always, WhatsApp when configured.
                'password_reset_channels' => $channels = $whatsapp->available() ? ['email', 'whatsapp'] : ['email'],
                'signup_channels' => $channels,
                // "Buy with AtomPay" in the app; off until Atomshop takes the orders (docs/api/orders.md).
                'in_app_orders' => InAppOrderService::enabled(),
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
            'complaint_topics' => collect(config('atompay.complaints.topics'))
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values(),
        ]]);
    }

    /** @return array<string, array{android: string|null, ios: string|null, web: string}> */
    private function apps(): array
    {
        $shop = rtrim((string) config('atompay.shop_url'), '/');
        $apps = config('atompay.apps');

        return [
            'atomshop' => ['android' => $apps['atomshop']['android'] ?: null, 'ios' => $apps['atomshop']['ios'] ?: null, 'web' => $shop],
            'atompay' => [
                'android' => config('atompay.api.store_url.android') ?: null,
                'ios' => config('atompay.api.store_url.ios') ?: null,
                'web' => rtrim((string) config('app.url'), '/'),
            ],
            'atomseller' => ['android' => $apps['atomseller']['android'] ?: null, 'ios' => $apps['atomseller']['ios'] ?: null, 'web' => $shop.'/smart-seller'],
            'atombrand' => ['android' => $apps['atombrand']['android'] ?: null, 'ios' => $apps['atombrand']['ios'] ?: null, 'web' => $shop.'/brand-partners'],
        ];
    }

    /** A bad value in .env must not break app launch: it becomes "no time given". */
    private function isoOrNull(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}

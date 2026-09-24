<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enums\EmploymentStatus;
use App\Models\Enums\IncomeSource;
use App\Services\Push\FcmClient;
use Illuminate\Http\JsonResponse;

/** Public, cacheable facts the app needs before (or without) signing in. */
class MetaController extends Controller
{
    /** Called on every launch: force-update check, links, support contacts. */
    public function appConfig(FcmClient $fcm): JsonResponse
    {
        $api = config('atompay.api');

        return response()->json(['data' => [
            'min_version' => $api['min_version'],
            'store_url' => $api['store_url'],
            'shop_url' => config('atompay.shop_url'),
            'password_reset_url' => $api['password_reset_url'],
            'support' => $api['support'],
            'features' => [
                'push' => $fcm->enabled(),
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

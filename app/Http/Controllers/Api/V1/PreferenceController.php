<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The customer's notification settings. Security emails (reset codes,
 * password changed) are not optional and are not listed here.
 */
class PreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->respond($request->user()->wantsEmailAlerts());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['email_alerts' => ['required', 'boolean']]);

        UserPreference::updateOrCreate(['user_id' => $request->user()->id], ['email_alerts' => $request->boolean('email_alerts')]);

        return $this->respond((bool) $data['email_alerts']);
    }

    private function respond(bool $emailAlerts): JsonResponse
    {
        return response()->json(['data' => ['email_alerts' => $emailAlerts]]);
    }
}

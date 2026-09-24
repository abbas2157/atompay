<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Push registration. Call after sign-in and whenever FCM rotates the token. */
class DeviceController extends Controller
{
    public function store(DeviceRequest $request): JsonResponse
    {
        /*
         * Keyed by the FCM token, not the user: a shared phone that signs
         * in as someone else moves to that account, so the previous owner
         * stops receiving pushes on a phone that is no longer theirs.
         */
        $device = Device::updateOrCreate(
            ['fcm_token_hash' => Device::hashToken($request->validated('fcm_token'))],
            [
                'user_id' => $request->user()->id,
                'access_token_id' => $request->user()->currentAccessToken()->getKey(),
                'fcm_token' => $request->validated('fcm_token'),
                'platform' => $request->validated('platform'),
                'app_version' => $request->validated('app_version'),
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['data' => [
            'id' => $device->id,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'registered_at' => $device->created_at->toIso8601String(),
        ]], $device->wasRecentlyCreated ? 201 : 200);
    }

    /** Stop pushes to this phone (e.g. the customer turned notifications off). */
    public function destroy(Request $request): Response
    {
        $request->validate(['fcm_token' => ['required', 'string', 'max:512']]);

        $request->user()->devices()
            ->where('fcm_token_hash', Device::hashToken($request->input('fcm_token')))
            ->delete();

        return response()->noContent();
    }
}

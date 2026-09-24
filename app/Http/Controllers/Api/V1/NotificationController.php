<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** The in-app inbox. Filled by `php artisan atompay:notify`. */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return NotificationResource::collection($user->appNotifications()->paginate(self::PER_PAGE))
            ->additional(['meta' => ['unread_count' => $user->appNotifications()->unread()->count()]]);
    }

    public function read(Request $request, int $notification): NotificationResource
    {
        $item = $request->user()->appNotifications()->findOrFail($notification);
        $item->read_at ??= now();
        $item->save();

        return new NotificationResource($item);
    }

    public function readAll(Request $request): Response
    {
        $request->user()->appNotifications()->unread()->update(['read_at' => now()]);

        return response()->noContent();
    }
}

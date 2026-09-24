<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SessionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** "Where you're signed in": one entry per live access token. */
class SessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()->tokens()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->get();

        return SessionResource::collection($tokens);
    }

    /** Signs one device out, and stops its pushes. */
    public function destroy(Request $request, int $session): Response
    {
        $token = $request->user()->tokens()->whereKey($session)->firstOrFail();

        $request->user()->devices()->where('access_token_id', $token->id)->delete();
        $token->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Auth\Concerns\LogsFailedLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Mobile sign-in against AtomShop's `users` table - the same accounts and
 * passwords as the website - answered with a Sanctum bearer token instead
 * of a session cookie. Only active customers are issued a token.
 */
class AuthController extends Controller
{
    use LogsFailedLogins;

    public function register(RegisterRequest $request, AccountService $accounts): JsonResponse
    {
        $user = $accounts->registerCustomer($request->validated());

        return $this->issueToken($user, $request->deviceName(), 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->verify($request);

        if (! $user) {
            $this->logFailedLogin($request, $request->credentials(), 'api');

            throw ValidationException::withMessages(['login' => 'Those details do not match an AtomShop account.']);
        }

        // Sellers, staff and blocked accounts share this table; the app is for customers.
        abort_unless($user->isCustomer(), 403, 'Please sign in with an AtomShop customer account.');

        return $this->issueToken($user, $request->deviceName());
    }

    /** Signs out this device only, and stops its pushes. */
    public function logout(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();
        $request->user()->devices()->where('access_token_id', $token->getKey())->delete();
        $token->delete();

        return response()->noContent();
    }

    /** Signs out every device - for a lost phone or a changed password. */
    public function logoutEverywhere(Request $request): Response
    {
        $request->user()->devices()->delete();
        $request->user()->tokens()->delete();

        return response()->noContent();
    }

    /**
     * Checks the password without starting a session. validate() never
     * logs anyone in, so nothing is written to the session store.
     */
    private function verify(LoginRequest $request): ?User
    {
        $guard = Auth::guard('web');

        foreach ($request->credentialAttempts() as $credentials) {
            if ($guard->validate($credentials)) {
                return $guard->getLastAttempted();
            }
        }

        return null;
    }

    private function issueToken(User $user, string $device, int $status = 200): JsonResponse
    {
        $expiresAt = now()->addDays(config('atompay.api.token_ttl_days'));
        $token = $user->createToken($device, ['*'], $expiresAt);

        return response()->json(['data' => [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => new UserResource($user->loadMissing('kycProfile')),
        ]], $status);
    }
}

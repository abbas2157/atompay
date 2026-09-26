<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Auth\Concerns\LogsFailedLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\AccountService;
use App\Services\PasswordResetService;
use App\Services\SignupService;
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

    /**
     * Sign-up step 1: details are checked and a code sent - by email when
     * `login` is an email, on WhatsApp when it is a mobile. No account
     * exists yet, hence 202 rather than 201.
     */
    public function register(RegisterRequest $request, SignupService $signups): JsonResponse
    {
        $signup = $signups->start($request->signup(), $request->ip());

        return response()->json(['data' => $signups->describe($signup)], 202);
    }

    /** Step 2: right code -> the account is created and this device signed in. */
    public function verifySignup(Request $request, SignupService $signups, AccountService $accounts): JsonResponse
    {
        $request->validate([
            'signup_id' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:12'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $signups->complete($signups->find($request->input('signup_id')), $request->input('code'));
        $accounts->sendWelcome($user);

        $device = trim((string) $request->input('device_name')) ?: config('atompay.api.default_device_name');

        return $this->issueToken($user, $device, 201);
    }

    /** A fresh code, on the same channel (60 s cooldown). */
    public function resendSignupCode(Request $request, SignupService $signups): JsonResponse
    {
        $request->validate(['signup_id' => ['required', 'string', 'max:64']]);

        $signup = $signups->resend($signups->find($request->input('signup_id')));

        return response()->json(['data' => $signups->describe($signup)]);
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

    /* ------------------------------------------------------- forgot password */

    /**
     * Step 1. Always 202 with the same shape, whether or not an account
     * matched - the app cannot be used to discover who has an account.
     */
    public function forgotPassword(Request $request, PasswordResetService $resets): JsonResponse
    {
        $request->validate(['login' => ['required', 'string', 'max:255']]);

        $reset = $resets->request($request->input('login'), $request->ip());

        return response()->json(['data' => [
            'request_id' => $reset->public_id,
            'channel' => $reset->channel,                    // email | whatsapp
            'destination' => $reset->destination,            // masked, e.g. "0300*****67"
            'expires_in' => max(0, (int) now()->diffInSeconds($reset->expires_at, false)),
            'resend_in' => $resets->resendIn($reset),
        ]], 202);
    }

    /** Step 2. A right code is swapped for a reset token. */
    public function verifyResetCode(Request $request, PasswordResetService $resets): JsonResponse
    {
        $request->validate([
            'request_id' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:12'],
        ]);

        $token = $resets->verify($resets->findRequest($request->input('request_id')), $request->input('code'));

        return response()->json(['data' => [
            'reset_token' => $token,
            'expires_in' => config('atompay.password_reset.token_ttl_minutes') * 60,
        ]]);
    }

    /**
     * Step 3. Sets the password, signs out every other device, and signs this
     * one in - the response is the same as a login.
     */
    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $resets): JsonResponse
    {
        $user = $resets->reset($request->input('reset_token'), $request->input('password'));

        return $this->issueToken($user, $request->deviceName());
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

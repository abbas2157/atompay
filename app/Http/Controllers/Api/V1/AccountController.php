<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AccountDeletionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /** The signed-in account - what the app loads on launch to confirm its token. */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('kycProfile'));
    }

    /**
     * Deletes the account here and on AtomShop.pk, after the password - checked
     * exactly as sign-in checks it. 409 `outstanding_balance` while money is owed.
     */
    public function destroy(Request $request, AccountDeletionService $deletions): Response
    {
        $request->validate(
            ['password' => ['required', 'string']],
            ['password.required' => 'Enter your password to confirm.'],
        );

        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => "That password isn't right."]);
        }

        $deletions->delete($user, 'app', $request->ip());

        return response()->noContent();
    }
}

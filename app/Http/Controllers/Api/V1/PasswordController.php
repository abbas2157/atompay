<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Change password while signed in. This is the AtomShop account's password,
 * so it changes on Atomshop.pk too. The phone that changed it stays signed
 * in; every other app sign-in (and its push device) is ended.
 */
class PasswordController extends Controller
{
    public function update(Request $request): Response
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            // Same rule as registration and reset.
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Choose a password different from your current one.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
        }

        $current = $user->currentAccessToken();

        DB::transaction(function () use ($user, $data, $current) {
            $user->forceFill([
                'password' => $data['password'],              // hashed by the model cast (bcrypt, same as AtomShop)
                'remember_token' => Str::random(60),           // ends "remember me" on the websites
            ])->save();

            // Push devices belong to a sign-in; one tied to none is ended too.
            $user->devices()->where(fn ($q) => $q->whereNull('access_token_id')->orWhere('access_token_id', '!=', $current->id))->delete();
            $user->tokens()->whereKeyNot($current->id)->delete();
        });

        if ($user->hasRealEmail()) {
            try {
                Mail::to($user->email, $user->name)->send(new PasswordChangedMail($user, keptThisDevice: true));
            } catch (Throwable $e) {
                Log::error('AtomPay email failed', ['mail' => PasswordChangedMail::class, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->noContent();
    }
}

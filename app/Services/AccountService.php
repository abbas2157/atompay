<?php

namespace App\Services;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creating AtomShop customer accounts from AtomPay - the website's
 * register form and the mobile app both come through here.
 */
class AccountService
{
    /**
     * Creates the same kind of record AtomShop checkout creates for a
     * walk-in buyer, so the account works on both sites.
     *
     * @param array{name: string, phone: string, email: string, password: string} $data validated RegisterRequest fields
     */
    public function registerCustomer(array $data): User
    {
        $user = new User;
        $user->forceFill([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => config('atompay.customer_role'),
            'status' => 'active',
            'joined_through' => 'Website',
        ])->save();

        return $user;
    }

    /** Called by both register endpoints. A mail failure never blocks sign-up. */
    public function sendWelcome(User $user): void
    {
        if (! $user->hasRealEmail()) {
            return;   // mobile-only sign-up: WhatsApp is for codes only, so no welcome
        }

        try {
            Mail::to($user->email, $user->name)->send(new WelcomeMail($user));
        } catch (Throwable $e) {
            Log::error('AtomPay welcome email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Support\Pakistan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Password sign-in for the website and the mobile app, against AtomShop's
 * `users` table.
 *
 * Laravel's own Auth::attempt() looks up `where phone = <as typed>` and
 * checks the password of the first row only. AtomShop's data does not fit
 * that: the same number is stored as 03..., +92 ..., "0300 1234567" and so
 * on, and is often on more than one account (checkout creates a new one for
 * a walk-in buyer). Forgot-password already finds the account by every form
 * of the number, so a customer could reset a password and then be told the
 * same password was wrong. Sign-in now looks the number up the same way and
 * tries each account it finds.
 */
class LoginService
{
    /** Accounts tried per sign-in - a number is never on more than a handful. */
    private const MAX_CANDIDATES = 10;

    /** The account `$login` names whose password is `$password`, or null. */
    public function attempt(string $login, string $password): ?User
    {
        return $this->candidates(trim($login))
            ->first(fn (User $user) => Hash::check($password, (string) $user->getAuthPassword()));
    }

    /**
     * Active customers first, then the most recently used - the same order
     * PasswordResetService picks the account to reset, so that account is
     * the one whose password is checked when two share a password.
     *
     * @return Collection<int, User>
     */
    private function candidates(string $login): Collection
    {
        if ($login === '') {
            return collect();
        }

        $query = User::query();

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $query->where('email', $login);
        } elseif (($mobile = Pakistan::normalizeMobile($login)) !== '') {
            $query->withMobile($mobile);
        } else {
            $query->where('phone', $login);
        }

        return $query
            ->orderByRaw('CASE WHEN role = ? AND status = ? THEN 0 ELSE 1 END', [config('atompay.customer_role'), 'active'])
            ->orderByDesc('last_login_at')->orderByDesc('id')
            ->limit(self::MAX_CANDIDATES)
            ->get();
    }
}

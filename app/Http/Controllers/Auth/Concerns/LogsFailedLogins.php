<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Shared by the web sign-in form and the mobile API. */
trait LogsFailedLogins
{
    /**
     * Support trail for "can't sign in" reports. The identifier is masked:
     * logs get copied into tickets and chat, and a full list of customer
     * emails and phone numbers should not travel with them. user_id is
     * enough to find the account, and the mask is enough to confirm which
     * address someone typed.
     *
     * @param array<string, string> $credentials [field => value, 'password' => ...]
     */
    protected function logFailedLogin(Request $request, array $credentials, string $channel = 'web'): void
    {
        $field = array_key_first($credentials);

        Log::info('AtomPay login failed', [
            'channel' => $channel,
            'field' => $field,
            'value' => $this->maskIdentifier($credentials[$field]),
            'user_id' => User::where($field, $credentials[$field])->value('id'),
            'ip' => $request->ip(),
        ]);
    }

    /** "ay****@gmail.com" / "0300*****21" - recognisable to its owner, useless to anyone else. */
    private function maskIdentifier(string $value): string
    {
        if (str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);

            return mb_substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
        }

        return mb_strlen($value) <= 6
            ? str_repeat('*', mb_strlen($value))
            : mb_substr($value, 0, 4).str_repeat('*', mb_strlen($value) - 6).mb_substr($value, -2);
    }
}

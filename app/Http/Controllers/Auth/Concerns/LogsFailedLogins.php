<?php

namespace App\Http\Controllers\Auth\Concerns;

use App\Models\User;
use App\Support\Mask;
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
            'value' => Mask::identifier($credentials[$field]),
            'user_id' => User::where($field, $credentials[$field])->value('id'),
            'ip' => $request->ip(),
        ]);
    }
}

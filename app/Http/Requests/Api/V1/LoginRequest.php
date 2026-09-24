<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\IdentifiesDevice;
use App\Http\Requests\LoginRequest as WebLoginRequest;
use App\Support\Pakistan;

class LoginRequest extends WebLoginRequest
{
    use IdentifiesDevice;

    public function rules(): array
    {
        return [...parent::rules(), ...$this->deviceRules()];
    }

    /**
     * Credentials to try, in order. A phone number is tried as typed, then
     * as 03XXXXXXXXX: most AtomShop accounts store the canonical form, but
     * a phone keyboard makes "+92 300 1234567" just as likely to be typed.
     *
     * @return list<array<string, string>>
     */
    public function credentialAttempts(): array
    {
        $credentials = $this->credentials();

        if (isset($credentials['email'])) {
            return [$credentials];
        }

        $canonical = Pakistan::normalizeMobile($credentials['phone']);

        return $canonical === '' || $canonical === $credentials['phone']
            ? [$credentials]
            : [$credentials, ['phone' => $canonical, 'password' => $credentials['password']]];
    }
}

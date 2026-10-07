<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\IdentifiesDevice;
use App\Http\Requests\LoginRequest as WebLoginRequest;

/** The website's sign-in fields, plus the device the token is for. See LoginService. */
class LoginRequest extends WebLoginRequest
{
    use IdentifiesDevice;

    public function rules(): array
    {
        return [...parent::rules(), ...$this->deviceRules()];
    }
}

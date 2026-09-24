<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\IdentifiesDevice;
use App\Http\Requests\RegisterRequest as WebRegisterRequest;

class RegisterRequest extends WebRegisterRequest
{
    use IdentifiesDevice;

    public function rules(): array
    {
        return [...parent::rules(), ...$this->deviceRules()];
    }
}

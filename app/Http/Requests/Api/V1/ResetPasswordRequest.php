<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\IdentifiesDevice;
use Illuminate\Foundation\Http\FormRequest;

/** Last step of forgot-password: the new password, and the device to sign in on. */
class ResetPasswordRequest extends FormRequest
{
    use IdentifiesDevice;

    public function rules(): array
    {
        return [
            'reset_token' => ['required', 'string', 'max:100'],
            // Same rule as registration (RegisterRequest).
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            ...$this->deviceRules(),
        ];
    }
}

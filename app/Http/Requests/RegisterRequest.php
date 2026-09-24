<?php

namespace App\Http\Requests;

use App\Rules\PakistanMobile;
use App\Support\Pakistan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', new PakistanMobile, Rule::unique('users', 'phone')->whereNull('deleted_at')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /*
         * AtomPay serves Pakistan only, and this number is how a customer is
         * reached about a payment falling due - so it is normalised to
         * 03XXXXXXXXX before the `unique` check, or the same person could
         * register twice as "+923001234567" and "0300 1234567".
         */
        $this->merge([
            'phone' => Pakistan::normalizeMobile($this->phone) ?: trim((string) $this->phone),
            'email' => mb_strtolower(trim((string) $this->email)),
        ]);
    }
}

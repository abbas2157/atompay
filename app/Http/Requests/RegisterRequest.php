<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->whereNull('deleted_at')],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/[^\d+]/', '', (string) $this->phone),
            'email' => mb_strtolower(trim((string) $this->email)),
        ]);
    }
}

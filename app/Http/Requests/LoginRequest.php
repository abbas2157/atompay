<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /** AtomShop lets customers sign in with either email or phone. */
    public function credentials(): array
    {
        $login = trim($this->input('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        return [$field => $login, 'password' => $this->input('password')];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\PendingSignup;
use App\Models\User;
use App\Rules\PakistanMobile;
use App\Support\Pakistan;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sign-up with ONE contact in `login`: an email address (the code goes by
 * email) or a Pakistani mobile (the code goes on WhatsApp).
 */
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:255', $this->contactRule(...)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function attributes(): array
    {
        return ['login' => 'email or mobile number'];
    }

    protected function prepareForValidation(): void
    {
        /*
         * Stored in canonical form so the duplicate check works: lower-case
         * email, 03XXXXXXXXX mobile. Otherwise the same person could sign up
         * twice as "+923001234567" and "0300 1234567". An unreadable value is
         * kept as typed so the rule can explain the problem.
         */
        $login = trim((string) $this->login);

        $this->merge([
            'login' => $this->isEmail($login) ? mb_strtolower($login) : (Pakistan::normalizeMobile($login) ?: $login),
        ]);
    }

    /** email | whatsapp - where the sign-up code goes. */
    public function channel(): string
    {
        return $this->isEmail($this->validated('login')) ? PendingSignup::EMAIL : PendingSignup::WHATSAPP;
    }

    /** @return array{name: string, channel: string, contact: string, password: string} */
    public function signup(): array
    {
        return [
            'name' => $this->validated('name'),
            'channel' => $this->channel(),
            'contact' => $this->validated('login'),
            'password' => $this->validated('password'),
        ];
    }

    private function contactRule(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (str_contains($value, '@')) {
            if (! $this->isEmail($value) || User::isPlaceholderEmail($value)) {
                $fail('Enter a valid email address.');
            } elseif (User::query()->where('email', $value)->exists()) {
                $fail('An account with this email already exists. Sign in or reset your password.');
            }

            return;
        }

        if (preg_match('/[a-z]/i', $value)) {
            $fail('Enter your email address or mobile number, e.g. 0300 1234567.');

            return;
        }

        (new PakistanMobile)->validate($attribute, $value, $fail);

        if (Pakistan::isValidMobile($value) && User::query()->withMobile(Pakistan::normalizeMobile($value))->exists()) {
            $fail('An account with this mobile number already exists. Sign in or reset your password.');
        }
    }

    private function isEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }
}

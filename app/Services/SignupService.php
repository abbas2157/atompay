<?php

namespace App\Services;

use App\Mail\SignupCodeMail;
use App\Models\PendingSignup;
use App\Models\User;
use App\Services\Messaging\WhatsAppClient;
use App\Support\Mask;
use App\Support\OneTimeCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sign-up with ONE contact, for the website and the app:
 *
 *   start(details)          -> pending sign-up; a code by email (email given)
 *                              or on WhatsApp (mobile given)
 *   complete(signup, code)  -> right code: the AtomShop account is created
 *   resend(signup)          -> a fresh code (60 s cooldown)
 *
 * Nothing is written to AtomShop's `users` until the code is proven, so a
 * mistyped contact never becomes an account.
 *
 * AtomShop requires an email on every account, so a mobile sign-up is given
 * a placeholder (User::placeholderEmailFor) that AtomPay never mails.
 *
 * Every code costs a WhatsApp message or an email, so one number or address
 * gets at most `hourly_limit` codes an hour, whoever asks.
 */
class SignupService
{
    public function __construct(
        private readonly WhatsAppClient $whatsapp,
        private readonly AccountService $accounts,
    ) {}

    /**
     * @param array{name: string, channel: string, contact: string, password: string} $data from RegisterRequest::signup()
     * @throws ValidationException (on `login`) when WhatsApp is unavailable, the hourly cap is hit, or delivery fails
     */
    public function start(array $data, ?string $ip = null): PendingSignup
    {
        $byEmail = $data['channel'] === PendingSignup::EMAIL;

        if (! $byEmail && ! $this->whatsapp->available()) {
            throw ValidationException::withMessages(['login' => "We can't send codes to mobile numbers right now. Sign up with your email address instead."]);
        }

        $this->guardHourlyLimit($byEmail ? 'email' : 'phone', $data['contact']);

        $signup = PendingSignup::create([
            'public_id' => (string) Str::uuid(),
            'name' => $data['name'],
            'channel' => $data['channel'],
            'email' => $byEmail ? $data['contact'] : null,
            'phone' => $byEmail ? null : $data['contact'],
            'password' => Hash::make($data['password']),
            'expires_at' => now()->addMinutes(config('atompay.signup.ttl_minutes')),
            'ip' => $ip,
        ]);

        try {
            $this->send($signup);
        } catch (ValidationException $e) {
            $signup->delete();

            throw $e;
        }

        return $signup;
    }

    /** @throws ValidationException (on `code`) when closed, cooling down, capped, or delivery fails */
    public function resend(?PendingSignup $signup): PendingSignup
    {
        $this->assertOpen($signup);

        if (($wait = $this->resendIn($signup)) > 0) {
            throw ValidationException::withMessages(['code' => "Please wait {$wait} seconds before asking for another code."]);
        }

        try {
            $this->guardHourlyLimit($signup->channel === PendingSignup::EMAIL ? 'email' : 'phone', $signup->destination());
            $this->send($signup);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['code' => collect($e->errors())->flatten()->first()]);
        }

        return $signup;
    }

    /** @throws ValidationException on `code` (wrong / expired) or `signup` (closed) */
    public function complete(?PendingSignup $signup, ?string $code): User
    {
        $this->assertOpen($signup);
        $signup->increment('attempts');

        if (! $signup->code_expires_at?->isFuture()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Send a new one.']);
        }

        if (! OneTimeCode::matches($signup->code_hash, $code)) {
            $left = config('atompay.signup.max_attempts') - $signup->attempts;

            throw ValidationException::withMessages($left > 0
                ? ['code' => "That code isn't right. {$left} ".Str::plural('try', $left).' left.']
                : ['signup' => 'Too many wrong codes. Please start again.']);
        }

        return $this->createAccount($signup);
    }

    public function find(?string $publicId): ?PendingSignup
    {
        return $publicId && Str::isUuid($publicId) ? PendingSignup::where('public_id', $publicId)->first() : null;
    }

    public function resendIn(PendingSignup $signup): int
    {
        return $signup->sent_at
            ? max(0, config('atompay.signup.resend_seconds') - (int) $signup->sent_at->diffInSeconds(now()))
            : 0;
    }

    /** Client-facing state of a pending sign-up (API responses, web verify page). */
    public function describe(PendingSignup $signup): array
    {
        return [
            'signup_id' => $signup->public_id,
            'channel' => $signup->channel,                              // email | whatsapp
            'destination' => Mask::identifier($signup->destination()),
            'expires_in' => max(0, (int) now()->diffInSeconds($signup->expires_at, false)),
            'resend_in' => $this->resendIn($signup),
        ];
    }

    /* ---------------------------------------------------------------- */

    private function createAccount(PendingSignup $signup): User
    {
        $byEmail = $signup->channel === PendingSignup::EMAIL;

        return DB::transaction(function () use ($signup, $byEmail) {
            // Someone may have taken the email or number while the code was in flight.
            $taken = $byEmail
                ? User::query()->where('email', $signup->email)->lockForUpdate()->exists()
                : User::query()->withMobile($signup->phone)->lockForUpdate()->exists();
            if ($taken) {
                throw ValidationException::withMessages(['signup' => 'An account with this '.($byEmail ? 'email' : 'mobile number').' was created while you were signing up. Please sign in instead.']);
            }

            // Already hashed; the model's `hashed` cast keeps a valid hash as it is.
            $user = $this->accounts->registerCustomer([
                'name' => $signup->name,
                'email' => $byEmail ? $signup->email : User::placeholderEmailFor($signup->phone),
                'phone' => $byEmail ? null : $signup->phone,
                'password' => $signup->password,
            ]);

            if ($byEmail) {
                $user->forceFill(['email_verified_at' => now()])->save();   // just proven
            }

            $signup->forceFill(['completed_at' => now(), 'user_id' => $user->id])->save();

            return $user;
        }, 3);
    }

    /** @throws ValidationException on `login` when the code could not be delivered */
    private function send(PendingSignup $signup): void
    {
        $code = OneTimeCode::generate();
        $minutes = config('atompay.signup.code_ttl_minutes');

        $sent = $signup->channel === PendingSignup::EMAIL
            ? $this->mail($signup, new SignupCodeMail(Str::before($signup->name, ' ') ?: $signup->name, $code, $minutes))
            : $this->whatsapp->sendOtp($signup->phone, $code);

        if (! $sent) {
            throw ValidationException::withMessages(['login' => $signup->channel === PendingSignup::EMAIL
                ? "We couldn't send a code to this email just now. Please check it and try again."
                : "We couldn't send a WhatsApp code to this number. Make sure it has WhatsApp, or sign up with your email."]);
        }

        $signup->forceFill([
            'code_hash' => OneTimeCode::hash($code),
            'code_expires_at' => now()->addMinutes($minutes),
            'sent_at' => now(),
            'sends' => $signup->sends + 1,
        ])->save();
    }

    private function mail(PendingSignup $signup, SignupCodeMail $mail): bool
    {
        try {
            Mail::to($signup->email, $signup->name)->send($mail);

            return true;
        } catch (Throwable $e) {
            Log::error('AtomPay sign-up code email failed', ['signup' => $signup->public_id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** @param 'phone'|'email' $column */
    private function guardHourlyLimit(string $column, string $value): void
    {
        $sentLastHour = PendingSignup::where($column, $value)->where('sent_at', '>', now()->subHour())->sum('sends');

        if ($sentLastHour >= config('atompay.signup.hourly_limit')) {
            throw ValidationException::withMessages(['login' => $column === 'phone'
                ? 'Too many codes have been sent to this number. Please try again in an hour.'
                : 'Too many codes have been sent to this email. Please try again in an hour.']);
        }
    }

    private function assertOpen(?PendingSignup $signup): void
    {
        if (! $signup || ! $signup->isOpen()) {
            throw ValidationException::withMessages(['signup' => 'This sign-up has expired. Please start again.']);
        }
    }
}

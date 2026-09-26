<?php

namespace App\Services;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Services\Messaging\WhatsAppClient;
use App\Support\Mask;
use App\Support\Pakistan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Forgot password, for the website and the mobile app:
 *
 *   request(login)       -> 6-digit code by email (email typed) or WhatsApp (mobile typed)
 *   verify(request, code) -> short-lived reset token
 *   reset(token, password) -> new password on the AtomShop account
 *
 * Replaces AtomShop's /password/reset/{uuid} link for AtomPay users; that
 * link never expires and needs no code, so anyone holding a uuid can take
 * the account.
 *
 * A request for an unknown email or number is answered exactly like a real
 * one (a row with user_id null whose codes never match), so the form cannot
 * be used to find out who has an account. Codes and tokens are stored only
 * as HMACs.
 */
class PasswordResetService
{
    public function __construct(private readonly WhatsAppClient $whatsapp) {}

    /**
     * @throws ValidationException when the input is not an email or Pakistani mobile,
     *         WhatsApp is not available, or the code could not be delivered
     */
    public function request(string $login, ?string $ip = null): PasswordReset
    {
        $login = trim($login);
        $byEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        if (! $byEmail) {
            if (Pakistan::normalizeMobile($login) === '') {
                throw ValidationException::withMessages(['login' => 'Enter your email address or mobile number, e.g. 0300 1234567.']);
            }
            if (! $this->whatsapp->available()) {
                throw ValidationException::withMessages(['login' => "Codes by WhatsApp aren't available right now. Enter your email address instead."]);
            }
        }

        $channel = $byEmail ? PasswordReset::CHANNEL_EMAIL : PasswordReset::CHANNEL_WHATSAPP;
        $destination = $byEmail ? mb_strtolower($login) : Pakistan::normalizeMobile($login);
        $user = $byEmail ? $this->findByEmail($destination) : $this->findByMobile($destination);

        // Asked again within the cooldown: keep the code already on its way.
        if ($user && ($recent = $this->recentOpenRequest($user, $channel))) {
            return $recent;
        }

        $code = $this->newCode();
        $reset = PasswordReset::create([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user && ! $this->overHourlyLimit($user) ? $user->id : null,
            'channel' => $channel,
            'destination' => Mask::identifier($destination),
            'code_hash' => $this->hash($code),
            'expires_at' => now()->addMinutes(config('atompay.password_reset.code_ttl_minutes')),
            'ip' => $ip,
        ]);

        if ($reset->user_id) {
            $this->send($reset, $user, $destination, $code);
        }

        return $reset;
    }

    /**
     * @return string the plain reset token (shown to the client once)
     * @throws ValidationException on a wrong, expired or exhausted code
     */
    public function verify(?PasswordReset $reset, string $code): string
    {
        if (! $reset || ! $reset->isOpen()) {
            throw ValidationException::withMessages(['code' => 'This code has expired. Request a new one.']);
        }

        $reset->increment('attempts');

        if (! $reset->user_id || ! hash_equals($reset->code_hash, $this->hash(trim($code)))) {
            $left = config('atompay.password_reset.max_attempts') - $reset->attempts;

            throw ValidationException::withMessages(['code' => $left > 0
                ? "That code isn't right. {$left} ".Str::plural('try', $left).' left.'
                : 'Too many wrong codes. Request a new one.']);
        }

        $token = Str::random(64);
        $reset->forceFill([
            'verified_at' => now(),
            'token_hash' => $this->hash($token),
            'token_expires_at' => now()->addMinutes(config('atompay.password_reset.token_ttl_minutes')),
        ])->save();

        return $token;
    }

    /**
     * Sets the new password on the AtomShop account - the one place besides
     * registration where AtomPay writes to `users`. Every AtomPay app token
     * and "remember me" cookie dies with the old password.
     *
     * @throws ValidationException when the token is unknown, used or expired
     */
    public function reset(string $token, string $password): User
    {
        $reset = PasswordReset::where('token_hash', $this->hash($token))->first();
        $user = $reset?->user;

        if (! $reset || $reset->used_at || ! $reset->token_expires_at?->isFuture() || ! $user?->isCustomer()) {
            throw ValidationException::withMessages(['reset_token' => 'This reset has expired. Please start again.']);
        }

        DB::transaction(function () use ($reset, $user, $password) {
            $user->forceFill([
                'password' => $password,                       // hashed by the model cast (bcrypt, same as AtomShop)
                'remember_token' => Str::random(60),
            ])->save();

            $reset->forceFill(['used_at' => now()])->save();
            PasswordReset::where('user_id', $user->id)->whereNull('used_at')->update(['expires_at' => now(), 'token_expires_at' => now()]);

            $user->devices()->delete();
            $user->tokens()->delete();
        });

        $this->mail($user, new PasswordChangedMail($user));

        return $user;
    }

    public function findRequest(string $publicId): ?PasswordReset
    {
        return Str::isUuid($publicId) ? PasswordReset::where('public_id', $publicId)->first() : null;
    }

    /** Seconds until another code may be sent for this request. */
    public function resendIn(PasswordReset $reset): int
    {
        return max(0, config('atompay.password_reset.resend_seconds') - (int) $reset->created_at->diffInSeconds(now()));
    }

    /* ---------------------------------------------------------------- */

    private function send(PasswordReset $reset, User $user, string $destination, string $code): void
    {
        $minutes = config('atompay.password_reset.code_ttl_minutes');

        $sent = $reset->channel === PasswordReset::CHANNEL_EMAIL
            ? $this->mail($user, new PasswordResetCodeMail($user->shortName(), $code, $minutes), $destination)
            : $this->whatsapp->sendOtp($destination, $code);

        if (! $sent) {
            $reset->forceFill(['expires_at' => now()])->save();

            throw ValidationException::withMessages(['login' => "We couldn't send your code just now. Please try again in a minute."]);
        }

        // Only the newest code works.
        PasswordReset::where('user_id', $user->id)->whereKeyNot($reset->id)->whereNull('used_at')
            ->update(['expires_at' => now()]);

        $reset->forceFill(['sent' => true])->save();
    }

    private function mail(User $user, $mailable, ?string $to = null): bool
    {
        try {
            Mail::to($to ?? $user->email, $user->name)->send($mailable);

            return true;
        } catch (Throwable $e) {
            Log::error('AtomPay email failed', ['mail' => $mailable::class, 'user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function findByEmail(string $email): ?User
    {
        return User::customers()->active()->where('email', $email)->first();
    }

    /**
     * AtomShop stores most numbers as 03XXXXXXXXX but some as +92 / 92 /
     * 3XXXXXXXXX, so every form is looked up. If one number is on several
     * accounts, the one used most recently wins.
     */
    private function findByMobile(string $local): ?User
    {
        $rest = substr($local, 1);   // 3001234567

        return User::customers()->active()
            ->whereIn('phone', [$local, $rest, '92'.$rest, '+92'.$rest, '0092'.$rest])
            ->orderByDesc('last_login_at')->orderByDesc('id')
            ->first();
    }

    private function recentOpenRequest(User $user, string $channel): ?PasswordReset
    {
        $reset = PasswordReset::where('user_id', $user->id)->where('channel', $channel)->where('sent', true)
            ->where('created_at', '>', now()->subSeconds(config('atompay.password_reset.resend_seconds')))
            ->latest('id')->first();

        return $reset?->isOpen() ? $reset : null;
    }

    private function overHourlyLimit(User $user): bool
    {
        return PasswordReset::where('user_id', $user->id)->where('sent', true)
            ->where('created_at', '>', now()->subHour())
            ->count() >= config('atompay.password_reset.hourly_limit');
    }

    private function newCode(): string
    {
        $length = config('atompay.password_reset.code_length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}

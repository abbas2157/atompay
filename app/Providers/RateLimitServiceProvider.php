<?php

namespace App\Providers;

use App\Support\Pakistan;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Named request limits, referenced from routes/web.php as `throttle:login`
 * and so on. Ceilings live in config/security.php.
 *
 * Two ideas run through all of them:
 *
 *  - Key by account when we know it, by IP only when we do not. Carriers in
 *    Pakistan put thousands of customers behind one address, so an IP-only
 *    limit punishes bystanders for one abuser.
 *  - Limit the expensive and the sensitive, not the cheap. A calculator quote
 *    is a read; a login attempt is a guess at someone's account.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $limits = config('security.rate_limits');

        // Backstop against crawl floods and scripted hammering.
        RateLimiter::for('global', fn (Request $r) => Limit::perMinute($limits['global'])->by($this->actor($r)));

        /*
         * Credential stuffing works by trying one password against many
         * accounts, so an attempt is counted twice: against the account being
         * guessed at, and against the source address. Beating both means
         * either slow guessing at one account or slow guessing at many.
         */
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute($limits['login'])->by($this->identifier($r).'|'.$r->ip()),
            Limit::perMinute($limits['login_ip'])->by($r->ip()),
        ]);

        /*
         * Accounts are free to create and carry a CNIC form behind them. Keyed
         * by the contact as well as the address: every try counts, typos
         * included, so an IP-only cap locked a whole carrier address out for
         * an hour after a few mistakes, whatever number was tried next.
         * SignupService also caps the codes sent to each contact.
         */
        RateLimiter::for('register', fn (Request $r) => [
            Limit::perHour($limits['register'])->by('register:'.$this->identifier($r).'|'.$r->ip()),
            Limit::perHour($limits['register_ip'])->by('register-ip:'.$r->ip()),
        ]);

        RateLimiter::for('quote', fn (Request $r) => Limit::perMinute($limits['quote'])->by($this->actor($r)));

        RateLimiter::for('assess', fn (Request $r) => [
            Limit::perMinute($limits['assess'])->by($this->actor($r)),
            Limit::perHour($limits['assess_hourly'])->by($this->actor($r)),
        ]);

        RateLimiter::for('application', fn (Request $r) => Limit::perMinute($limits['application'])->by($this->actor($r)));

        // Signed-in mobile API traffic. Runs after auth:sanctum, so it keys by account.
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute($limits['api'])->by($this->actor($r)));

        // Stops a signed-in account walking the document ids looking for
        // someone else's CNIC. The ownership check refuses them; this stops
        // them trying thousands of times.
        RateLimiter::for('documents', fn (Request $r) => Limit::perMinute($limits['documents'])->by($this->actor($r)));

        // Deleting the account asks for the password, so it is a guess like a login.
        RateLimiter::for('account_delete', fn (Request $r) => Limit::perMinute($limits['account_delete'])->by('delete:'.$this->actor($r)));

        /*
         * Sending codes (forgot password, sign-up resend). What stops someone's
         * phone being spammed is the services, not these: PasswordResetService
         * and SignupService send at most one code per 60 s and a few an hour,
         * and a repeat inside the cooldown just returns the code already sent.
         * These only stop floods. At 3 a minute, a double-firing tap plus a
         * "resend" was a 429 after the second try.
         *
         * Verifying is keyed by the pending reset / sign-up as well as the
         * address, so one customer's typos don't lock out their neighbours;
         * each code also dies after 5 wrong tries.
         */
        RateLimiter::for('otp_request', fn (Request $r) => [
            Limit::perMinute($limits['otp_request'])->by('otp:'.$this->identifier($r).'|'.$r->ip()),
            Limit::perMinute($limits['otp_request_ip'])->by('otp-ip:'.$r->ip()),
        ]);
        RateLimiter::for('otp_verify', fn (Request $r) => [
            Limit::perMinute($limits['otp_verify'])->by('otp-verify:'.$this->identifier($r).'|'.$r->ip()),
            Limit::perMinute($limits['otp_verify_ip'])->by('otp-verify-ip:'.$r->ip()),
        ]);
    }

    /** Account id when signed in, client address otherwise. */
    private function actor(Request $request): string
    {
        return $request->user()?->getAuthIdentifier()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'ip:'.$request->ip();
    }

    /**
     * The email or phone being attempted, normalised so case, or "+92 300..."
     * against "0300...", cannot dodge the limit. A resend names the pending
     * sign-up / reset instead (the website keeps it in the session), so one
     * person's resends do not use up everyone's on the same address.
     */
    private function identifier(Request $request): string
    {
        $login = trim((string) $request->input('login'));

        if ($login !== '') {
            return Pakistan::normalizeMobile($login) ?: mb_strtolower($login);
        }

        return (string) ($request->input('signup_id')
            ?? $request->input('request_id')
            ?? ($request->filled('reset_token') ? 'reset:'.hash('sha256', (string) $request->input('reset_token')) : null)
            ?? ($request->hasSession() ? 'session:'.$request->session()->getId() : ''));
    }
}

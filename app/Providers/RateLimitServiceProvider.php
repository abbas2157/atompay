<?php

namespace App\Providers;

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

        // Accounts are free to create and carry a CNIC form behind them.
        RateLimiter::for('register', fn (Request $r) => Limit::perHour($limits['register'])->by($r->ip()));

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

        /*
         * Forgot password. Every code costs a WhatsApp message or an email, and
         * an unthrottled form is a free way to spam someone's phone, so the
         * target and the source are both capped (on top of the per-account
         * cooldown and hourly cap in PasswordResetService). Verifying is capped
         * per IP; each code also dies after 5 wrong tries.
         */
        RateLimiter::for('otp_request', fn (Request $r) => [
            Limit::perMinute($limits['otp_request'])->by('otp:'.$this->identifier($r).'|'.$r->ip()),
            Limit::perMinute($limits['otp_request_ip'])->by('otp-ip:'.$r->ip()),
        ]);
        RateLimiter::for('otp_verify', fn (Request $r) => Limit::perMinute($limits['otp_verify'])->by('otp-verify:'.$r->ip()));
    }

    /** Account id when signed in, client address otherwise. */
    private function actor(Request $request): string
    {
        return $request->user()?->getAuthIdentifier()
            ? 'user:'.$request->user()->getAuthIdentifier()
            : 'ip:'.$request->ip();
    }

    /** The email or phone being attempted, normalised so case cannot dodge the limit. */
    private function identifier(Request $request): string
    {
        return mb_strtolower(trim((string) $request->input('login')));
    }
}

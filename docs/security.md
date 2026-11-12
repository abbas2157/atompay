# Security

What AtomPay does to protect customer data, where each control lives, and what
is deliberately *not* covered. This app holds CNIC scans, selfies, declared
income and credit decisions for Pakistani consumers — the sensitive material is
the identity documents, not the money.

## Controls in place

| Layer | Control | Where |
|---|---|---|
| Response headers | CSP, HSTS, framing, referrer, permissions | `app/Http/Middleware/SecurityHeaders.php`, `config/security.php` |
| Request rate | Named per-route limits, keyed by account or IP | `app/Providers/RateLimitServiceProvider.php` |
| Brute force | Login counted against both account and source IP | `RateLimitServiceProvider::boot()` |
| Document access | Owner-or-staff check, private disk, no public URL | `Account/DocumentController.php` |
| Access audit | Every staff view of a CNIC scan, selfie, verification form or the review page (CNIC number) is logged: who, what, when, IP | `Services/KycAccessRecorder.php`, `atompay_kyc_access_logs` |
| Mass assignment | AtomShop tables fully guarded | `Models/Concerns/BelongsToAtomShop.php` |
| Input | Form requests on every write path | `app/Http/Requests/` |
| Uploads | `image`/`mimes` + size caps, stored outside the web root; content checks + ClamAV before storing | `Rules/SafeUpload.php`, `Services/Security/UploadScanner.php` |
| Server | Dotfiles, dumps and framework dirs refused | `.htaccess`, `public/.htaccess` |
| Transport | HTTPS forced in production | `AppServiceProvider::boot()` |
| Password reset | One-time code (email / WhatsApp), hashed, 10 min, 5 tries, no account enumeration; reset revokes every app token | `Services/PasswordResetService.php` |
| Email opt-out | Signed unsubscribe URLs; one-click (RFC 8058) is CSRF-exempt only for that path | `Web/EmailAlertsController.php`, `bootstrap/app.php` |

## Upload scanning

Every KYC upload — the customer's CNIC front/back and selfie, and staff's signed
verification form — passes `SafeUpload` before it is stored:

1. **Structural checks** (always on). The type is sniffed from the bytes, not the
   name. An image must decode and must not carry `<?php`, a phar stub or a
   `<script>` (polyglot files). A PDF must have a PDF header and no
   `/JavaScript`, `/JS`, `/Launch`, `/EmbeddedFile`, `/RichMedia`, `/XFA`,
   `/SubmitForm`, `/ImportData` or `/GoToE`, including hex-escaped names.
2. **ClamAV**, when `ATOMPAY_CLAMAV_SOCKET` names a clamd socket. The file is
   streamed (INSTREAM), so clamd needs no access to PHP's temp directory. If
   clamd is configured but down, uploads are **refused** with "try again in a
   few minutes" — set `ATOMPAY_CLAMAV_FAIL_CLOSED=false` only to keep KYC open
   through a clamd outage on structural checks alone.

A rejected file is not kept; the log records the user, the reason, the type,
size and SHA-256.

## Access audit

`atompay_kyc_access_logs` records each time someone other than the customer
opens a CNIC scan, selfie or verification form, or the staff review page (which
shows the CNIC number): viewer, customer, item, IP, user agent and time. The
row is written before the file is sent, so a failed write fails the view.
Customers viewing their own documents are not logged. Rows are append-only and
survive account deletion; they name no customer data beyond the account id.
The latest 20 are shown at the bottom of `/staff/assessments/{id}`.

## Password reset

`PasswordResetService` replaces AtomShop's reset for AtomPay customers. AtomShop's own flow
(`/password/reset/{uuid}` in AtomShop's `LoginController`) sets a new password for whoever
opens that URL. It needs no code and never expires, and the uuid is the only secret. AtomPay therefore never
exposes a user's `uuid` (it was removed from the API's `/me`) and never links to that page.

AtomPay's flow:

- **Proof of ownership.** A 6-digit code is sent by email (email typed) or WhatsApp (mobile typed).
  It's stored as an HMAC, lasts 10 minutes, allows 5 attempts, and only the newest code works.
- **No enumeration.** An unknown email or number gets the identical response, and a row is
  still written with `user_id` null, so timing and shape match too.
- **Spam limits.** The same account gets no second code within 60 s, and at most 5 codes an hour. Per-IP
  and per-identifier rate limits apply too, because every code costs a WhatsApp message or an email.
- **After the code,** a random 64-character reset token (also stored as an HMAC) lasts 15 minutes and
  works once.
- **On reset:** new bcrypt password (AtomShop-compatible), `remember_token` rotated, every
  AtomPay app token and push device revoked, and a "password changed" email sent.
- **Customers only.** Staff and seller accounts must reset through AtomShop.

Laravel supplies the rest of the baseline: CSRF tokens on every form, prepared
statements through Eloquent, `{{ }}` escaping in Blade, hashed passwords,
session regeneration on login and logout.

## Content-Security-Policy

The policy is assembled in `SecurityHeaders::contentSecurityPolicy()` from
`config/security.php`. It is the control that matters most here: it is what
stops an injected `<script>` from reading a signed-in customer's CNIC off the
page or posting their session token elsewhere.

Two entries are compromises, made deliberately and worth re-examining if either
dependency changes:

- **`script-src 'unsafe-eval'`** — Alpine evaluates `x-data` / `x-show`
  expressions with the `Function` constructor. Removing this needs Alpine's CSP
  build, which does not support the expressions this app uses.
- **`style-src 'unsafe-inline'`** — progress bars set `style="width: N%"` from
  server data. Style injection is far less dangerous than script injection.

Everything else is locked down: `default-src 'self'`, `frame-ancestors 'none'`,
`object-src 'none'`, `base-uri 'self'`, `form-action 'self'`.

The inline JSON-LD blocks in `components/seo.blade.php` carry a per-request
nonce. Without it some browsers drop the block and the structured data never
reaches crawlers — a silent SEO regression, not a visible error.

**When changing the policy**, set `SECURITY_CSP_REPORT_ONLY=true` first. The
browser then reports violations to the console instead of breaking the page.
Check the homepage (Alpine calculator), `/my/application` (file inputs) and
`/staff/assessments` before switching it back.

The CSP also widens automatically while `npm run dev` is running, to allow the
Vite dev server and its HMR socket.

**Google Analytics.** When `GOOGLE_ANALYTICS_ID` is set, Google's GA4 hosts
(`config('security.csp.analytics')`) are added to `script-src`, `img-src` and
`connect-src`, and both gtag tags carry the nonce. With no id, neither the tag
nor the hosts appear. The tag is never rendered on `/staff/*` (URLs carry
customer assessment ids), error pages have none (they are self-contained by
design), and Google signals / ad personalisation are turned off. Allowing
`*.googletagmanager.com` means a script from Google's tag host runs on pages
that show a customer's CNIC; that is the price of GA, so leave the id empty if
that trade-off is ever unacceptable.

## Rate limits

Defined in `RateLimitServiceProvider`, ceilings in `config/security.php`:

| Limiter | Cap | Keyed by |
|---|---|---|
| `global` | 600/min | account, or IP when anonymous |
| `login` | 5/min **and** 20/min | identifier+IP, and IP |
| `register` | 10/hour **and** 30/hour | email/mobile+IP, and IP |
| `quote` | 60/min | account or IP |
| `assess` | 10/min, 40/hour | account or IP |
| `application` | 10/min | account |
| `documents` | 60/min | account |
| `otp_request` | 10/min **and** 30/min | email/mobile (or pending request)+IP, and IP |
| `otp_verify` | 10/min **and** 30/min | pending request+IP, and IP |

The OTP limits only stop floods. How many codes are actually *sent* is capped in
`PasswordResetService` / `SignupService` (one per 60 s, a few per hour), and a
repeat inside the cooldown returns the code already sent instead of a 429.

Two principles shape these. **Key by account where we know it** — Pakistani
mobile carriers put thousands of customers behind one address, so an IP-only
limit punishes bystanders for one abuser. **Count a login attempt twice**, once
against the account being guessed at and once against the source: credential
stuffing tries one password against many accounts, and the per-IP limit is what
catches that shape of attack.

Exceeding a limit renders `resources/views/errors/429.blade.php`.

### Behind a CDN or load balancer

Rate limiting keys on the client IP. If a proxy sits in front and
`TRUSTED_PROXIES` is unset, **every visitor looks like the proxy** and they all
share one bucket — the limits then fire for innocent users while doing nothing
against an attacker. HSTS also stops being sent, because the request appears to
be plain HTTP.

Set `TRUSTED_PROXIES` to the proxy addresses, or `*` if the app is genuinely
unreachable except through it. Never `*` on a directly reachable host: it lets
anyone spoof their address with a header and walk past every limit.

## Error pages

`resources/views/errors/` covers 401, 403, 404, 419, 429, 500 and 503, sharing
`components/layouts/error.blade.php`.

They are deliberately self-contained — inline CSS, no `@vite`, no Alpine, no
auth lookup, no database query. An error page is usually rendered *because* one
of those is broken; if the 500 page called `@vite` and the manifest were
missing, rendering it would throw the same exception again and the visitor would
get a raw stack trace. The palette is duplicated from `resources/css/app.css`
and has to be kept in step by hand — that is the price of the isolation.

`errors/503.blade.php` doubles as the maintenance screen, pre-rendered by
`php artisan down --render="errors::503"` into `storage/framework/down`. Because
that render happens as the app goes offline, the page uses plain paths and never
calls `route()`.

## Production checklist

```ini
APP_DEBUG=false                 # a stack trace names your file paths and queries
APP_ENV=production
SESSION_SECURE_COOKIE=true      # cookie never travels over plain HTTP
SESSION_ENCRYPT=true            # session files unreadable to others on the box
SECURITY_CSP_ENABLED=true
SECURITY_HSTS_ENABLED=true
```

Also on the server:

- Document root is `public/`, never the project root. The root `.htaccess` is a
  XAMPP development shim; serving from the project root exposes `.env`,
  `storage/` and `vendor/`.
- `expose_php = Off` in `php.ini`. PHP appends `X-Powered-By` after Laravel has
  finished, so middleware cannot remove it — only the server can. The
  `.htaccess` files unset it as a fallback.
- `ServerTokens Prod` and `ServerSignature Off` in `httpd.conf`. Neither can be
  set from `.htaccess`.
- KYC documents outside both project trees — see `docs/shared-storage.md`.

Verify after deploying:

```bash
curl -sI https://atompay.shop | grep -iE 'content-security|strict-transport|x-frame|x-content-type|referrer|permissions'
curl -s -o /dev/null -w '%{http_code}\n' https://atompay.shop/.env          # expect 403 or 404
curl -s -o /dev/null -w '%{http_code}\n' https://atompay.shop/storage/logs/laravel.log
vendor/bin/phpunit --filter SecurityTest
```

## What is not covered

Named honestly, so nobody assumes otherwise:

- **No two-factor authentication**, for customers or for staff. Staff accounts
  can approve credit limits; 2FA on the staff roles is the highest-value
  addition left.
- **Virus scanning depends on clamd.** Without `ATOMPAY_CLAMAV_SOCKET` only the
  structural checks run, and those cannot see active content hidden inside a
  compressed PDF object stream. `atompay:deploy-check` warns when it is off.
- **AtomShop's admin panel is outside the access log.** It reads the same shared
  KYC documents; views there are not recorded by AtomPay.
- **Accounts AtomShop created** (at its own checkout) may have an unproven email.
  Every AtomPay sign-up proves its email or mobile with a one-time code first.
- **No automated dependency scanning.** Run `composer audit` and `npm audit` as
  part of deploying.
- **No WAF or bot detection.** The rate limits blunt scripted abuse; they do not
  stop a distributed, patient attacker.
- **Sessions are not shared with AtomShop**, and the two apps authenticate
  independently against the same `users` table.

## Reporting

Security issues in AtomPay affect AtomShop customers' identity documents. Treat
a report as urgent and do not file it in a public tracker.

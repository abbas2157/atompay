# AtomPay Mobile — Project Memory

Decisions and facts that aren't obvious from the code. Newest first. Add an entry
whenever a decision is made or something surprising is discovered, and say **why**.

---

### 2026-09-24 — v1 API completed (application, dashboard, plans, calculator, push, inbox)

- **Notifications come from a sweep, not events.** Limits and address checks are decided in
  **two** apps (AtomPay `/staff` and AtomShop's admin panel, see
  `docs/atomshop-admin-verification-prompt.md`), so hooks in AtomPay's controllers would miss
  half of them. `php artisan atompay:notify` runs every 10 minutes, reads state, and uses a unique
  `dedupe_key` per announcement (`assessment:{id}:{status}`, `kyc:{id}:{status}:{date}`,
  `instalment:{id}:{due|overdue}:{days}`), so reruns are harmless. It looks back
  `lookback_days` (3), which means the first production run announces decisions from the last 3 days.
- **Reminders only go out 09:00–21:00 PKT.** The app runs in UTC, and the sweep converts times
  itself (`config('atompay.notifications')`).
- **AtomPay has its own device table, not AtomShop's `fcm_tokens`.** Those tokens belong to the
  AtomShop app, so a push sent to them would open the wrong app. Devices are tied to the access
  token (`access_token_id`), so logout stops pushes. A device is keyed by the FCM token hash, so a
  shared phone moves to whoever signed in last.
- **FCM uses HTTP v1 without an SDK.** `FcmClient` signs the service-account JWT with openssl and
  caches the OAuth token for 55 minutes. When `FCM_CREDENTIALS` is unset, push is a no-op and the
  inbox still fills. On Windows/XAMPP, `openssl_pkey_new` needs an explicit `config` path
  (see the push test).
- **Staff notes are customer-facing.** The web banner already showed the assessment's `notes` and
  `verification_notes`, so the API does too (in the banner, the assessment once decided, and the KYC
  rejection push). This closes PRD open question 4. Reviewers should write notes knowing the
  customer reads them.
- **The provisional limit is hidden until decided.** `CreditAssessmentResource` nulls
  `approved_limit`, `max_instalment`, `approved_tenure` and `notes` while pending, matching the web limit card
  (which only shows the active limit).
- **Reapplying stays allowed at any time**, as on the web ("Improve my limit"). Each submission
  is a new pending row. `POST /application` returns 409 `profile_required` only when no profile exists.
- **Banner wording lives in `StatusBanner`.** The Blade partial and `/dashboard` share it. A new state was added,
  "Tell us about your income" (a profile without an assessment), which only happens through the app,
  where identity and income are separate steps.
- **Business-rule refusals are `409 {message, code}`** (`App\Exceptions\Api\BusinessRuleException`).
  Codes are listed in `docs/api/conventions.md`, and the app switches on `code`.
- **Don't put a `data` key in a JsonResource.** Laravel then skips the `{data: …}` wrapper
  for single resources. The notification's deep-link field is named `payload` for this reason.
- **AtomShop tenures are 3–12 (all months) locally.** Tests must not assume a tenure is
  unavailable; they pick one from the live config.

### 2026-09-24 — Phase 1 API shipped (auth + profile)

- **Tokens use a separate table, `atompay_personal_access_tokens`.** AtomShop's DB already has
  Sanctum's `personal_access_tokens` (≈1,285 rows) with `tokenable_type = App\Models\User`,
  which is the same class name AtomPay uses. A shared table would let every AtomShop app token
  authenticate on AtomPay. Wired through `App\Models\PersonalAccessToken` and
  `Sanctum::usePersonalAccessTokenModel()` in `AppServiceProvider`. A test pins this:
  `test_an_atomshop_app_token_does_not_open_atompay`.
- **The API is token-only.** `config/sanctum.php` sets `stateful: []` and `guard: []`, so a web
  session cookie can never authenticate an `/api` call (which rules out CSRF-style confusion).
- **Tokens expire after 30 days** (`config('atompay.api.token_ttl_days')`) and there's no refresh token.
  This keeps the design simple and the re-login cost is low. Tokens are pruned daily.
- **Login uses `Auth::guard('web')->validate()`, not `attempt()`.** It checks the password
  without touching the session.
- **Phone login tries the number as typed, then in `03XXXXXXXXX` form.** In the DB, of about 2,291
  customer rows, 1,160 are canonical `03XXXXXXXXX`, 1,014 are NULL, and the rest are `+92…`
  (34), `3XXXXXXXXX` (16) or junk (67). The web login still tries only as typed. It could
  adopt `Api\V1\LoginRequest::credentialAttempts()` later.
- **Non-customers get 403 and no token.** On protected routes the `customer` middleware
  **revokes** the token when an account stops being an active customer (blocked, role change).
- **Profile = KYC Section 1 only.** The web submits Sections 1 + 3 together. The app splits them
  (profile now, financial application in Phase 2). A KYC-only profile doesn't appear
  in the staff queue until an assessment exists. That's acceptable, because staff act on assessments.
- **Account fields are read-only.** Name, email, phone and password live on AtomShop's `users`
  row, which AtomPay only reads (the one exception is registration, which creates the row
  exactly as AtomShop checkout does, in `AccountService`).
- **Refactors made along the way:** registration moved to `App\Services\AccountService`, and
  failed-login logging moved to the `Auth\Concerns\LogsFailedLogins` trait. The Section 1 rules moved to
  `Requests\Concerns\ValidatesKycIdentity`, shared by the web `KycApplicationRequest` and the API
  `KycProfileRequest`.
- **Apache passes `Authorization`.** Both `.htaccess` files already set `HTTP_AUTHORIZATION`.
  This was verified end-to-end via `http://localhost/atompay/api/v1`.

### Standing facts

- Production web and API: `https://atompay.shop` (API at `/api/v1`). Local: `http://localhost/atompay`.
- One MySQL DB (`atomshop`) is shared with AtomShop. AtomPay's migrations are tracked in
  `atompay_migrations`, and it writes only `atompay_*` tables.
- Business numbers live in `config/atompay.php`: limit = 30% of income, max instalment =
  min(10% of income, disposable income), down payment 20–60%, markup =
  per_month% × months × (price − advance).
- Staff roles are `admin`, `amos`, `manager` and `recovery`. The customer role is `customer`, and they need `status = active`.
- KYC documents live on the private `kyc` disk (shared directory with AtomShop admin) and are never
  given a public URL.
- AtomShop has its own `fcm_tokens` table for the shop app. AtomPay deliberately doesn't use it (it uses `atompay_devices`).
- AtomShop's password reset page is `{ATOMSHOP_URL}/password/forgot`, exposed via `/app-config`.

### Proposed (not yet confirmed by the team)

- Flutter stack: Riverpod, go_router, dio, freezed and flutter_secure_storage. Swap in Bloc
  only if the team already knows it well, and decide before Phase 1 app work starts.

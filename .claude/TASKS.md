# AtomPay Mobile — Tasks

Tick items as they land (`[x]`). Keep this list in phase order, and put new work under the right phase.

## Phase 0 — Planning

- [x] PRD, architecture, rules, design, tasks, memory (`.claude/`)
- [x] API contract docs: `docs/api/` (index `README.md` + one file per area)
- [ ] Decide on the open questions in PRD §8 (register throttle, in-app payments, store identity)

## API (Laravel) — v1 complete ✅ 2026-09-24

32 endpoints, all covered by feature tests. The contract is `docs/api/`.

- [x] Sanctum, token-only, tokens in `atompay_personal_access_tokens`, 30-day expiry, daily prune
- [x] Auth: register, login (email or any phone format), logout, logout-all, sessions list/revoke
- [x] Refuse or revoke non-customer and blocked accounts (403)
- [x] `/me`, `/cities`
- [x] Profile (KYC §1): read with AtomShop prefill, multipart submit, own-document streaming
- [x] Application (KYC §3): read, submit (409 `profile_required`), history. Provisional limit hidden until decided
- [x] `/dashboard`: limit card, `StatusBanner` (shared with web), stepper, next due, unread count
- [x] `/plans` (+ `?include=completed`), `/plans/{order}` with schedule, owner-scoped
- [x] Public `/app-config` (min version, store/reset/support links), `/options`, `/calculator`, `/quote` (field errors), `/estimate`
- [x] `/devices` (FCM registration, tied to the sign-in, moves between accounts)
- [x] `/notifications` inbox: list, read, read-all
- [x] `atompay:notify` sweep every 10 min: limit decisions (either app), KYC outcomes, due/overdue reminders (09:00–21:00 PKT), idempotent
- [x] FCM HTTP v1 client (no SDK), removes dead tokens
- [x] Refactors: `AccountService`, `LogsFailedLogins`, `ValidatesKycIdentity`, `ValidatesFinancialProfile`, `StatusBanner`
- [x] 2026-09-26 Forgot password with OTP (email / WhatsApp via AtomShop's `auth_otp`) on the web + `/auth/password/*`
- [x] 2026-09-26 Emails: code, password changed, welcome, and every alert (with one-click unsubscribe). `GET/PATCH /me/preferences`
- [x] 2026-09-26 `application_received` notification. `uuid` removed from `/me`

### Production rollout

- [ ] `php artisan migrate` (6 new tables/columns: `atompay_personal_access_tokens`, `atompay_devices`, `atompay_notifications` (+ `emailed_at`), `atompay_password_resets`, `atompay_user_preferences`)
- [ ] **Real SMTP in `.env`** (copy AtomShop's `MAIL_*`). `MAIL_MAILER=log` in production would put reset codes in the log file instead of sending them
- [ ] `WHATSAPP_TOKEN` + `WHATSAPP_PHONE_NUMBER_ID`, using AtomShop's number, **after rotating the leaked token** (`docs/atomshop-password-reset-fix.md`)
- [ ] AtomShop side: fix `/password/reset/{uuid}` and move the WhatsApp token to `.env` (the same brief)
- [ ] Cron: `* * * * * cd /var/www/atompay.shop && php artisan schedule:run` (for notifications and token pruning; see `docs/deploy.md`)
- [ ] Create the Firebase project, download the service-account JSON, and set `FCM_CREDENTIALS` (outside the web root)
- [ ] Set `ATOMPAY_APP_MIN_*`, `ATOMPAY_APP_STORE_*` and `ATOMPAY_SUPPORT_*` in `.env`, then run `php artisan config:cache`
- [ ] Revisit the `register` throttle (5/hour/IP) for mobile carriers (CGNAT) before launch

## App (Flutter)

### Phase 1 — Foundation, Auth & Profile

- [ ] Create the project `atompay_mobile` (org id TBD), with flavors dev/staging/prod
- [ ] Lints (`very_good_analysis`), folder skeleton per ARCHITECTURE.md
- [ ] Theme: `AppTokens`, `AppTheme` (light), bundled fonts
- [ ] Core: `ApiClient` (dio), `AuthInterceptor`, `ApiException` (incl. `Conflict(code)`) + `ErrorMapper`, `TokenStorage`
- [ ] Core widgets: PrimaryButton, GhostButton, AppTextField, CnicField, MobileField, StatusPill, Banner, ErrorView
- [ ] `pk_formatters.dart` covering CNIC/mobile masks + validators, with unit tests mirroring `app/Support/Pakistan.php`
- [ ] Launch: `GET /app-config`, then force-update gate, then `GET /me`
- [ ] Auth: models, repository, controller (sealed state), go_router redirect. Screens: Splash, Sign in, Register
- [ ] Handle 401/403/409/422/429 end to end
- [ ] Profile: read screen + form (prefill, city picker, camera/gallery, compression, upload progress)
- [ ] Authenticated document viewer (no disk cache, `FLAG_SECURE`)
- [ ] Forgot password: 3 native screens (login, code with a resend timer, new password), `/auth/password/*`, channels from `/app-config`
- [ ] Account: sessions list with "sign out this device", sign out everywhere, an email-alerts switch (`/me/preferences`)
- [ ] Widget tests: login, register, profile form validation

### Phase 2 — Application & Dashboard

- [ ] Dashboard: StatusBanner (routes on `banner.action`), LimitCard, ProcessStepper, next due card
- [ ] Application form driven by `/options` (employer field shown only when `has_employer` is true)
- [ ] Application status + history screen
- [ ] Income estimator (public, labelled "estimate")

### Phase 3 — Plans & Calculator

- [ ] Plans list (active / history tabs), plan detail schedule
- [ ] Calculator (bounds from `/calculator`, debounced `/quote`)
- [ ] "Shop on AtomShop" hand-off (product `shop_url`, `shop_url`)

### Phase 4 — Engagement

- [ ] FCM setup, `atompay_default` channel, `POST /devices` after sign-in + on token refresh
- [ ] Push tap routing by `data.screen`, and mark read via `notification_id`
- [ ] Notifications inbox (paginated) + unread badge from the dashboard
- [ ] Urdu (RTL), biometric unlock, dark mode

### Release

- [ ] Android: `network_security_config` (dev cleartext only), release signing, obfuscation
- [ ] iOS: camera/photo usage descriptions, push entitlement, ATS
- [ ] Internal test build (Play internal testing / TestFlight)

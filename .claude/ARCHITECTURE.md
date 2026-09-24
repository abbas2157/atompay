# AtomPay Mobile — Architecture

## 1. System overview

```
 ┌──────────────────────┐        HTTPS + Bearer token        ┌────────────────────────────────┐
 │  AtomPay Flutter app │ ─────────────────────────────────▶ │  AtomPay Laravel 12 (this repo) │
 │  Android / iOS       │ ◀───────────── JSON ────────────── │  /api/v1/*  (routes/api.php)    │
 └──────────────────────┘                                    │  web pages  (routes/web.php)    │
            │                                                └───────┬─────────────┬──────────┘
            │ browser hand-off                                       │             │
            ▼                                                        ▼             ▼
 ┌──────────────────────┐                         ┌───────────────────────┐  ┌──────────────────┐
 │  atomshop.pk         │ ─────── same DB ──────▶ │ MySQL `atomshop`       │  │ KYC disk (private)│
 │  (AtomShop Laravel)  │                         │ AtomShop tables + ...  │  │ shared by both    │
 └──────────────────────┘                         │ atompay_* tables       │  │ apps              │
                                                  └───────────────────────┘  └──────────────────┘
```

- **One database.** AtomPay reads AtomShop's `users`, `customers`, `orders`,
  `order_instalments`, `cities` and `installment_calculators`. It **owns** only the `atompay_*` tables:
  `atompay_kyc_profiles`, `atompay_credit_assessments`, `atompay_personal_access_tokens`,
  `atompay_devices`, `atompay_notifications`, and `atompay_migrations`.
- **One account.** The app signs in against AtomShop's `users` table (bcrypt), so customers use
  the same credentials on AtomShop and AtomPay.
- **Separate tokens.** AtomShop has its own Sanctum `personal_access_tokens` table with the
  same `App\Models\User` type. AtomPay deliberately uses `atompay_personal_access_tokens`
  so neither app's tokens open the other.

## 2. Backend (Laravel) — API layer

| Concern | Where |
|---|---|
| Routes | `routes/api.php`, prefixed `/api/v1`, named `api.v1.*` |
| Auth | Sanctum bearer tokens, token-only (`config/sanctum.php`: `stateful: []`, `guard: []`) |
| Token model | `App\Models\PersonalAccessToken` → `atompay_personal_access_tokens`, registered in `AppServiceProvider` |
| Token TTL | `config('atompay.api.token_ttl_days')` = 30. Expired tokens are pruned daily (`routes/console.php`) |
| Customer gate | `customer` middleware (`EnsureUserIsCustomer`). On API routes it revokes the token and returns 403 |
| Controllers | `app/Http/Controllers/Api/V1/*` are thin, and they call the **same services as the web** |
| Requests | `app/Http/Requests/Api/V1/*` extend or share the web rules (`Concerns/ValidatesKycIdentity`) |
| Responses | `app/Http/Resources/Api/V1/*`, which is the only place response shapes are defined |
| Errors | `bootstrap/app.php` forces JSON for `api/*`. Validation is Laravel's `{message, errors}` |
| Rate limits | `RateLimitServiceProvider` + `config/security.php` (`login`, `register`, `application`, `documents`, `api`) |
| Contract | `docs/api/` (index: `docs/api/README.md`, one file per area), which is updated in the same commit as any API change |
| Tests | `tests/Feature/Api/*` exercise real bearer tokens on the shared DB inside a transaction |

**Rule:** business logic lives in `app/Services`. The web controller and API controller for a
feature call the same service method. An API controller never re-implements a calculation.

Which service backs which endpoint:

| Service | Endpoints |
|---|---|
| `AccountService` | `POST /auth/register` (also the web register form) |
| `KycService` | `GET/POST /profile` |
| `CreditAssessmentService` | `/application`, `/dashboard` (limit via `summary()`), `/estimate` |
| `StatusBanner` | `/dashboard` banner (also the web dashboard banner) |
| `ProcessTracker` | `/dashboard` stages |
| `PaymentScheduleService` | `/plans`, `/plans/{order}`, `/dashboard` next due |
| `InstalmentQuoteService` | `/calculator`, `/quote` |
| `NotificationService` + `Push\FcmClient` | `atompay:notify` sweep → `atompay_notifications` + FCM |

### Notifications

```
cron (every 10 min) → atompay:notify ─┬─ decided assessments (last 3 days) ─┐
                                      ├─ verified/rejected KYC (last 3 days) ┼─▶ NotificationService::notify()
                                      └─ due/overdue instalments (09–21 PKT) ┘      │ dedupe_key unique → once only
                                                                                     ├─ insert atompay_notifications (inbox)
                                                                                     └─ FcmClient → each atompay_devices row
                                                                                          (UNREGISTERED → device deleted)
```

It's a sweep rather than event hooks because decisions are also made in AtomShop's admin
panel, which AtomPay code never runs in.

## 3. Flutter app

### Stack

| Concern | Choice | Why |
|---|---|---|
| Language | Dart 3 (null-safe, records, sealed classes) | |
| State + DI | **Riverpod** (`flutter_riverpod` + `riverpod_annotation`/generator) | Compile-safe DI, easy testing, and async state (`AsyncValue`) fits API screens |
| Navigation | **go_router** | Declarative routes plus an auth redirect guard |
| HTTP | **dio** | Interceptors (auth header, 401 handling), multipart upload with progress, timeouts |
| Models | **freezed** + **json_serializable** | Immutable models, `copyWith`, sealed unions for results |
| Secure storage | **flutter_secure_storage** | Keychain / Keystore for the bearer token |
| Images | **image_picker** + **flutter_image_compress** | Camera/gallery, compress to ≤ 4 MB |
| Formatting | **intl** | `PKR 123,456`, dates |
| Fonts | **google_fonts** (or bundled TTFs) | Bricolage Grotesque + Inter, which match the web |
| Screenshots | `flutter_windowmanager` / platform channel | `FLAG_SECURE` on KYC screens |
| Push (Phase 4) | `firebase_messaging` + `flutter_local_notifications` | Server sends FCM HTTP v1. The app registers via `POST /devices` |
| Testing | `flutter_test`, `mocktail`, `http_mock_adapter` | |

### Folder structure (feature-first, layered)

```
lib/
  main.dart                     # bootstrap: flavors, ProviderScope, error zone
  app.dart                      # MaterialApp.router, theme, locale
  core/
    config/env.dart             # base URLs per flavor (dev / staging / prod)
    network/
      api_client.dart           # Dio instance, base options, timeouts
      auth_interceptor.dart     # adds Bearer, on 401 → clear token + emit signed-out
      api_exception.dart        # sealed: Unauthorized, Forbidden, Validation(errors), RateLimited(retryAfter), Network, Server
      error_mapper.dart         # DioException → ApiException
    storage/token_storage.dart  # flutter_secure_storage wrapper
    router/app_router.dart      # go_router + redirect on auth state
    theme/                      # tokens.dart, app_theme.dart, typography.dart (see DESIGN.md)
    utils/                      # pk_formatters.dart (CNIC/mobile masks + validators), money.dart
    widgets/                    # AppButton, AppTextField, StatusPill, LimitCard, Stepper, ErrorView...
  features/
    auth/
      data/      auth_api.dart, auth_repository.dart, models/ (auth_session, user)
      domain/    auth_state.dart (sealed: unknown | signedIn(user) | signedOut)
      presentation/ splash_screen, login_screen, register_screen, controllers/
    profile/
      data/      profile_api.dart, profile_repository.dart, models/ (kyc_profile, city)
      presentation/ profile_screen, profile_form_screen, document_viewer, controllers/
    account/     presentation/ account_screen
    dashboard/   (phase 2)
    application/ (phase 2)
    plans/       (phase 3)
test/            # mirrors lib/
```

Layer rules:

- **presentation** → **data** (repositories) → **api** (dio). Widgets never touch Dio.
- Repositories return models or throw `ApiException`. Controllers (Riverpod `AsyncNotifier`)
  turn those into UI state.
- Models mirror `docs/api/*.md` exactly (snake_case JSON ↔ camelCase Dart via
  `@JsonKey`/`fieldRename: FieldRename.snake`).

### Auth flow

```
launch → read token ─┬─ none ───────────────▶ /login
                     └─ found → GET /me ─┬─ 200 → signedIn(user) → /home
                                         ├─ 401 → clear → /login
                                         └─ network error → keep cached user, show offline banner
login/register → store token → signedIn(user)
any 401 (interceptor) → clear token → signedOut → router redirect /login
403 on protected route → server already revoked token → same as 401 + show message
```

### Environments

| Flavor | Base URL |
|---|---|
| `dev` | `http://10.0.2.2/atompay/api/v1` (emulator) / LAN IP (device) |
| `staging` | TBD |
| `prod` | `https://atompay.shop/api/v1` |

Set with `--dart-define=FLAVOR=prod` (or `--dart-define-from-file`). Cleartext HTTP is allowed in
`dev` only (Android `network_security_config` debug overlay).

## 4. Versioning

- The API is versioned in the path (`/api/v1`). Within v1 you can add fields, but never
  remove, rename or retype them. A breaking change requires `/api/v2`.
- The app sends `device_name` at sign-in. Consider adding `X-App-Version` in Phase 2 so
  the server can force-upgrade very old clients.

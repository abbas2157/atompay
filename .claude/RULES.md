# AtomPay Mobile — Rules

Rules for anyone (human or Claude) working on the AtomPay API or the Flutter app.
If a rule blocks you, raise it. Don't work around it quietly.

## 1. The contract

1. **`docs/api/` is the source of truth** for every endpoint, field and error. An API
   change and its doc update go in the **same commit**. The Flutter models follow the doc,
   not guesses from a response.
2. **Never break v1.** You can add fields and endpoints, but never rename, remove or retype
   them. A breaking change requires `/api/v2`.
3. Every response shape is defined in an `app/Http/Resources/Api/V1/*` resource. Never return a
   raw model or `->toArray()`.
4. Every new endpoint gets a feature test in `tests/Feature/Api/` that uses a real bearer token.

## 2. Backend (Laravel)

1. **Reuse the services.** API controllers are thin and call the same `app/Services` method as
   the web controller. Duplicated business logic is a bug waiting to diverge.
2. **Share validation.** API requests extend the web request or use a shared `Concerns/` trait.
   Never copy rules.
3. **AtomShop tables are read-only.** Models on AtomShop tables use `BelongsToAtomShop`
   (everything guarded). AtomPay writes only to `atompay_*` tables. The single exception is
   registration, which creates a `users` row exactly as AtomShop checkout does (`AccountService`).
4. **Tokens live in `atompay_personal_access_tokens`.** Never point Sanctum at AtomShop's
   `personal_access_tokens`.
5. Every write route has a named rate limit (`config/security.php`). New limits are keyed
   by account when signed in and by IP only when anonymous.
6. Only active customers get tokens. Staff, sellers and blocked users are refused with a
   `403`.
7. Every business number comes from `config/atompay.php`. There are no magic numbers in controllers or the app.
8. Run `php artisan test` before committing. All tests must pass.

## 3. Flutter app

### Business logic

1. **The server does the math.** Limits, instalment caps, risk, plan pricing and disposable
   income come from the API. The app displays them and never computes and submits them.
   (A purely visual preview is fine if it is labelled "estimate".)
2. **Money is `int` rupees.** Never use `double` for money. Format only at the edge
   (`PKR 123,456`).
3. CNIC and mobile are validated and formatted client-side for UX, but the server's
   normalised value (`cnic`, `mobile`) is what the app stores and compares.

### Security & privacy

4. The bearer token lives **only** in `flutter_secure_storage`. It never goes in
   SharedPreferences, logs, analytics, crash reports or deep links.
5. **No PII in logs, analytics or crash reports:** no CNIC, selfie, DOB, address, income, email,
   phone or token. Use the user `id` if you must correlate.
6. KYC screens and document viewers set `FLAG_SECURE` (no screenshots or recents preview).
7. Document images load with the auth header and are **not** written to the disk cache.
8. Release builds use HTTPS only. Cleartext is allowed in the `dev` flavor only.
9. Release builds are obfuscated (`--obfuscate --split-debug-info`).

### Code

10. Structure is feature-first (`lib/features/<feature>/{data,domain,presentation}`). Widgets
    never import Dio. Repositories never import Flutter widgets.
11. Riverpod for all state and DI. There are no global singletons and no `setState` for anything that
    touches the network.
12. Models use `freezed` + `json_serializable`. Keep `build_runner` output committed or
    regenerated in CI, and pick one approach consistently.
13. Errors are mapped once (`core/network/error_mapper.dart`) into a sealed `ApiException`. Screens
    switch on that type and never on status codes or strings.
14. Every screen that loads data handles **loading, empty, error (with retry) and data**.
15. `flutter analyze` must be clean, using `very_good_analysis` or `flutter_lints` plus strict
    casts, inference and raw types.
16. Don't hard-code user-facing strings. Put them in ARB files (`lib/l10n/`) from the start, because
    Urdu is planned.
17. Don't hard-code colours or sizes in widgets. Use `AppTokens` / `Theme.of(context)` (see
    DESIGN.md).

### Testing

18. Unit tests for formatters/validators (CNIC, mobile, money) and repositories (with
    `http_mock_adapter`).
19. Widget tests for the login, register and profile forms, including validation errors from a
    `422`.
20. Test the 401 → sign-out path explicitly.

## 4. Git

- Branch from `main`. Keep commits small and use an imperative subject line ("Add profile document viewer").
- Never commit `.env`, keystores, `google-services.json` with production keys, or tokens.
- Update `.claude/TASKS.md` (tick items) and `.claude/MEMORY.md` (new decisions) as part of the
  work, not afterwards.

# AtomPay Mobile API — v1

The contract the AtomPay Flutter app is built against. It has one file per area, and they're
listed below.

- **Server code:** `routes/api.php`, `app/Http/Controllers/Api/V1/`, `app/Http/Resources/Api/V1/`
- **Tests** that pin every example in these files: `tests/Feature/Api/`
- **Status:** Every v1 endpoint is live (27 endpoints).

| | |
|---|---|
| Production base URL | `https://atompay.shop/api/v1` |
| Local (XAMPP) | `http://localhost/atompay/api/v1` |
| Android emulator | `http://10.0.2.2/atompay/api/v1` |
| Auth | `Authorization: Bearer <token>` (Sanctum). Always send `Accept: application/json` |

**Start with [conventions.md](conventions.md).** It covers the envelope, money, dates, CNIC/phone
formats, every error status, the `409` codes, rate limits, and the token lifecycle. Every other file assumes it.

## Files

| File | Covers |
|---|---|
| [conventions.md](conventions.md) | Base URLs, conventions, errors, rate limits, token lifecycle |
| [auth.md](auth.md) | Register, login, logout, logout everywhere, signed-in sessions |
| [account.md](account.md) | `GET /me`, `kyc_status` values |
| [profile.md](profile.md) | KYC Section 1 (identity + CNIC/selfie uploads), own documents, cities |
| [application.md](application.md) | KYC Section 3 (income → limit), the assessment object, history |
| [dashboard.md](dashboard.md) | Home screen: limit card, status banner, stepper, next due |
| [plans.md](plans.md) | AtomShop instalment orders and their schedules |
| [calculator.md](calculator.md) | Calculator bounds, plan quote, income estimate (all public) |
| [app-config.md](app-config.md) | Launch config (force update, links, support) and form options (public) |
| [notifications.md](notifications.md) | Push device registration, push payload, notifications inbox |

## Endpoint index

| Method | Path | Auth | Doc |
|---|---|---|---|
| GET | `/app-config` | – | [app-config.md](app-config.md) |
| GET | `/options` | – | [app-config.md](app-config.md) |
| GET | `/calculator` | – | [calculator.md](calculator.md) |
| POST | `/quote` | – | [calculator.md](calculator.md) |
| POST | `/estimate` | – | [calculator.md](calculator.md) |
| POST | `/auth/register` | – | [auth.md](auth.md) |
| POST | `/auth/login` | – | [auth.md](auth.md) |
| POST | `/auth/logout` | ✓ | [auth.md](auth.md) |
| POST | `/auth/logout-all` | ✓ | [auth.md](auth.md) |
| GET | `/auth/sessions` | ✓ | [auth.md](auth.md) |
| DELETE | `/auth/sessions/{id}` | ✓ | [auth.md](auth.md) |
| GET | `/me` | ✓ | [account.md](account.md) |
| GET | `/dashboard` | ✓ | [dashboard.md](dashboard.md) |
| GET | `/profile` | ✓ | [profile.md](profile.md) |
| POST | `/profile` | ✓ | [profile.md](profile.md) |
| GET | `/profile/documents/{document}` | ✓ | [profile.md](profile.md) |
| GET | `/cities` | ✓ | [profile.md](profile.md) |
| GET | `/application` | ✓ | [application.md](application.md) |
| POST | `/application` | ✓ | [application.md](application.md) |
| GET | `/application/history` | ✓ | [application.md](application.md) |
| GET | `/plans` | ✓ | [plans.md](plans.md) |
| GET | `/plans/{order_id}` | ✓ | [plans.md](plans.md) |
| POST | `/devices` | ✓ | [notifications.md](notifications.md) |
| DELETE | `/devices` | ✓ | [notifications.md](notifications.md) |
| GET | `/notifications` | ✓ | [notifications.md](notifications.md) |
| POST | `/notifications/{id}/read` | ✓ | [notifications.md](notifications.md) |
| POST | `/notifications/read-all` | ✓ | [notifications.md](notifications.md) |

## Suggested app flow

1. Launch: `GET /app-config` (force update?), then `GET /me` if a token is stored.
2. Sign in or register, then `POST /devices` (if push is enabled).
3. Home: `GET /dashboard`, and follow `banner.action` to the profile or application form.
4. Profile form: `GET /profile` + `GET /cities`, then `POST /profile` (multipart).
5. Income form: `GET /options` + `GET /application`, then `POST /application`.
6. Plans tab: `GET /plans`, then `GET /plans/{id}`.
7. Inbox: `GET /notifications`. A push tap routes by `data.screen`.

## Rules for changing this API

- Update the endpoint's file here **in the same commit** as the code change, and add a
  changelog line below.
- Within v1 you can add fields and endpoints, but never rename, remove or retype them. A breaking change
  requires `/api/v2`.
- Every endpoint has a feature test in `tests/Feature/Api/`.

## Changelog

| Date | Change |
|---|---|
| 2026-09-24 | Docs split into `docs/api/` (one file per area). No API change. |
| 2026-09-24 | v1 complete: `/app-config`, `/options`, `/calculator`, `/quote`, `/estimate`, `/dashboard`, `/application` (+ history), `/plans`, `/devices`, `/notifications`, `/auth/sessions`. Logout now also unregisters the device. |
| 2026-09-24 | v1 Phase 1: auth (register, login, logout, logout-all), `/me`, `/profile`, `/profile/documents/*`, `/cities`. |

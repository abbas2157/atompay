# Auth & sessions

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

## `POST /auth/register`

Creates an AtomShop customer account, which also works on atomshop.pk, and signs it in.

```json
{
  "name": "Ayesha Khan",
  "phone": "+92 300 1234567",
  "email": "ayesha@example.com",
  "password": "at-least-8-chars",
  "password_confirmation": "at-least-8-chars",
  "device_name": "Pixel 7 · Android 14"
}
```

| Field | Rules |
|---|---|
| `name` | required, ≤ 255 |
| `phone` | required, Pakistani **mobile** (landlines are refused with an explanation), unique |
| `email` | required, valid email, ≤ 255, unique (lower-cased by the server) |
| `password` | required, ≥ 8, must match `password_confirmation` |
| `device_name` | optional, ≤ 100. Shown in "signed-in devices" later. Default is `AtomPay app` |

**`201 Created`**, with the same body as login (below).

**`422`** examples: `phone` gets *"That looks like a landline. Enter a mobile number so we can text you about payments."*, and `email` gets *"The email has already been taken."*

## `POST /auth/login`

```json
{ "login": "ayesha@example.com", "password": "…", "device_name": "Pixel 7 · Android 14" }
```

`login` is an email **or** a phone number. The server first tries a phone number exactly as
typed, then in `03XXXXXXXXX` form, so `+92 300 1234567` works.

**`200 OK`**

```json
{
  "data": {
    "token": "20|atompay_Xu97xvn4yedZao8gERnNirmcXXAZtjHCook97B6P8cfcbf8b",
    "token_type": "Bearer",
    "expires_at": "2026-10-24T06:47:14+00:00",
    "user": { "...": "same object as GET /me - see account.md" }
  }
}
```

**`422`** means wrong credentials: `{"errors": {"login": ["Those details do not match an AtomShop account."]}}`
**`403`** means the credentials are right but the account isn't an active customer. No token is issued.

## `POST /auth/logout` → `204 No Content`

Revokes this device's token **and unregisters this device from push**.

## `POST /auth/logout-all` → `204 No Content`

Revokes every token and removes every registered device.

## `GET /auth/sessions`

Lists where the customer is signed in (tokens that haven't expired), newest first. The token
itself is never returned.

```json
{
  "data": [
    { "id": 140, "device_name": "Pixel 7 · Android 14", "current": true,
      "signed_in_at": "2026-09-24T07:06:04+00:00", "last_used_at": "2026-09-24T07:06:07+00:00",
      "expires_at": "2026-10-24T07:06:04+00:00" }
  ]
}
```

## `DELETE /auth/sessions/{id}` → `204 No Content`

Signs that device out and stops its pushes. Returns `404` for an id that isn't yours. Deleting
the `current` session is the same as `POST /auth/logout`.

## Forgot password (OTP)

This is three public calls. What the customer types decides the channel:

| Typed | Code sent by |
|---|---|
| Email address | Email |
| Pakistani mobile (any format) | WhatsApp, on AtomShop's business number (`auth_otp` template with a copy-code button) |

Check `GET /app-config` → `features.password_reset_channels` before offering the mobile option.

Rules:

- The code is **6 digits**, lasts **10 minutes** and allows **5 attempts**. Only the newest code works.
- Asking again within **60 s** returns the same request and doesn't send a second code. Use `resend_in`
  for the "Resend" timer.
- An account gets at most 5 codes per hour. Rate limits: 3 requests/min per identifier + IP, 10/min per IP,
  and 10 verify/reset calls per minute per IP.
- **Unknown accounts get exactly the same response.** Don't tell the customer "no account found".
  Say "If this belongs to an AtomShop account, we've sent a code".
- Only active **customer** accounts can reset here. Staff and sellers use AtomShop.

### `POST /auth/password/forgot` → `202 Accepted`

```json
{ "login": "0300 1234567" }
```
```json
{
  "data": {
    "request_id": "34fc3e23-3052-4366-88c4-ffcb8c2a1919",
    "channel": "whatsapp",
    "destination": "0300*****67",
    "expires_in": 599,
    "resend_in": 60
  }
}
```

| `422` on `login` | Meaning |
|---|---|
| "Enter your email address or mobile number, e.g. 0300 1234567." | Neither an email nor a Pakistani mobile. |
| "Codes by WhatsApp aren't available right now. Enter your email address instead." | WhatsApp isn't configured on the server. |
| "We couldn't send your code just now. Please try again in a minute." | The email or WhatsApp provider failed. |

### `POST /auth/password/verify`

```json
{ "request_id": "34fc3e23-…", "code": "863230" }
```
```json
{ "data": { "reset_token": "N0gfGI…(64 chars)", "expires_in": 900 } }
```

**`422`** on `code` returns *"That code isn't right. 4 tries left."*, *"Too many wrong codes. Request a new one."*,
or *"This code has expired. Request a new one."* The last two mean go back to step 1.

Use `autofillHints: [AutofillHints.oneTimeCode]` on the code field. The WhatsApp message has
a copy-code button.

### `POST /auth/password/reset`

```json
{ "reset_token": "N0gfGI…", "password": "new-pass-456", "password_confirmation": "new-pass-456", "device_name": "Pixel 7" }
```

On success:

- the password changes, and works on AtomShop.pk too
- **every** AtomPay app sign-in and push registration is revoked
- a "password changed" email is sent
- this device is signed in

The response is **the same as `POST /auth/login`** (`token`, `expires_at`, `user`).

**`422`**: `password` uses the same rules as registration (≥ 8, confirmed). `reset_token` returns *"This reset has
expired. Please start again."* (after 15 minutes, or when used already).

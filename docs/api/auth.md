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

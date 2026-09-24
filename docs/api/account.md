# Account

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

## `GET /me`

```json
{
  "data": {
    "id": 5012,
    "uuid": "af2397e0-4799-482b-97d1-71efbe54d0f4",
    "name": "Ayesha Khan",
    "short_name": "Ayesha K.",
    "email": "ayesha@example.com",
    "email_verified": false,
    "phone": "03001234567",
    "phone_formatted": "0300 1234567",
    "member_since": "2026-09-24",
    "kyc_status": "not_started"
  }
}
```

`phone` / `phone_formatted` can be `null` on older AtomShop accounts.

`kyc_status` is one of:

| Value | Meaning | Suggested UI |
|---|---|---|
| `not_started` | No profile submitted yet | "Complete your profile" call to action |
| `pending` | Submitted; waiting for the address visit and review | "Under review" badge |
| `verified` | Address verified by AtomPay staff | Green tick |
| `rejected` | Verification failed | "Contact support" + let the customer resubmit |

The account (name, email, phone, password) belongs to AtomShop and is **read-only** in v1.
Password reset is not in v1. Link to AtomShop's flow for now.

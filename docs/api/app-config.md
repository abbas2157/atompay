# App config & options

[← API index](README.md) · [Conventions, errors & limits](conventions.md)

## `GET /app-config` (public)

Call this on every launch, before `GET /me`.

```json
{
  "data": {
    "min_version": { "android": "1.0.0", "ios": "1.0.0" },
    "store_url": { "android": "https://play.google.com/…", "ios": null },
    "shop_url": "https://atomshop.pk",
    "password_reset_url": "https://atompay.shop/forgot-password",
    "privacy_url": "https://atompay.shop/privacy",
    "terms_url": null,
    "account_deletion_url": "https://atompay.shop/delete-account",
    "support": { "phone": null, "whatsapp": null, "email": null },
    "maintenance": { "active": false, "message": null, "until": null },
    "apps": {
      "atomshop":   { "android": "https://play.google.com/store/apps/details?id=com.ecommerce.atomshop", "ios": "https://apps.apple.com/us/app/atomshop/id6744655747", "web": "https://atomshop.pk" },
      "atompay":    { "android": "https://play.google.com/store/apps/details?id=com.ecommerce.atompay", "ios": "https://apps.apple.com/us/app/atompay-pay-in-steps/id6817231756", "web": "https://atompay.shop" },
      "atomseller": { "android": "https://play.google.com/store/apps/details?id=com.ecommerce.atomseller", "ios": "https://apps.apple.com/us/app/atom-seller-sell-more/id6817232001", "web": "https://atomshop.pk/smart-seller" },
      "atombrand":  { "android": null, "ios": "https://apps.apple.com/us/app/atombrand-brand-partner/id6820037360", "web": "https://atomshop.pk/brand-partners" }
    },
    "features": { "push": true, "voice": true, "password_reset_channels": ["email", "whatsapp"], "signup_channels": ["email", "whatsapp"] }
  }
}
```

- **`maintenance.active: true`** → show the blocking Maintenance screen (no back), with `message` if set
  and "Back by about {time}" from `until` (ISO-8601, or `null` for no time). **Try again** re-calls
  `/app-config`. Set with `ATOMPAY_APP_MAINTENANCE`, `_MESSAGE` and `_UNTIL`. A `503` from any endpoint
  (`php artisan down`) means the same thing, without a message or time.
- **`apps`** feeds the Ecosystem screen. A `null` store link means the app isn't published there yet:
  show "Android soon" / "iOS soon" instead of a link.
- **`features.voice`** is `false` to hide the Ask Atom microphone without a new build
  (`ATOMPAY_APP_VOICE`).

- If the app's version is **lower than** `min_version[platform]`, show a blocking "Update
  required" screen that links to `store_url[platform]`.
- **Forgot password** is native in the app ([auth.md](auth.md#forgot-password-otp)). `password_reset_url` is the website's own reset page, a fallback only.
- `privacy_url` is always set (`ATOMPAY_PRIVACY_URL`, otherwise the site's `/privacy` page). `terms_url` is `null`
  until terms are published, so hide that link then. `account_deletion_url` is the web page for deleting an account without the
  app (for the Play Console listing): `ATOMPAY_ACCOUNT_DELETION_URL`, otherwise the site's `/delete-account` page. In the app, deletion is
  [`POST /me/delete`](account.md#post-medelete).
- `features.password_reset_channels` is `["email"]` or `["email", "whatsapp"]`. Hide the mobile-number option when WhatsApp isn't listed.
- `features.signup_channels` has the same values. When WhatsApp isn't listed, the sign-up field should ask for
  an email only, because a mobile number would be refused.
- `features.push` is `false` when the server has no FCM credentials. Skip the notification
  permission prompt in that case.
- Hide any `support` entry that is `null`.

## `GET /options` (public)

Values and labels for the application form's pickers. Always send `value`, and display `label`.

```json
{
  "data": {
    "employment_statuses": [
      { "value": "salaried", "label": "Salaried", "has_employer": true },
      { "value": "self_employed", "label": "Self-employed", "has_employer": true },
      { "value": "business_owner", "label": "Business owner", "has_employer": true },
      { "value": "freelancer", "label": "Freelancer", "has_employer": false },
      { "value": "retired", "label": "Retired", "has_employer": false },
      { "value": "unemployed", "label": "Unemployed", "has_employer": false }
    ],
    "income_sources": [
      { "value": "salary", "label": "Salary" }, { "value": "business", "label": "Business" },
      { "value": "rental", "label": "Rental" }, { "value": "remittance", "label": "Remittance" },
      { "value": "pension", "label": "Pension" }, { "value": "other", "label": "Other" }
    ],
    "complaint_topics": [
      { "value": "payment", "label": "A payment" }, { "value": "order", "label": "An order" },
      { "value": "limit", "label": "My limit or application" }, { "value": "account", "label": "My account or sign-in" },
      { "value": "other", "label": "Something else" }
    ]
  }
}
```

`complaint_topics` fills the "What is it about?" picker on the complaint form ([complaints.md](complaints.md)).

When `has_employer` is `true`, the **employer / business name** field is required. Show it
only in that case.

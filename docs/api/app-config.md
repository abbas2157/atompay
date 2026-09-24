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
    "password_reset_url": "https://atomshop.pk/password/forgot",
    "support": { "phone": null, "whatsapp": null, "email": null },
    "features": { "push": true }
  }
}
```

- If the app's version is **lower than** `min_version[platform]`, show a blocking "Update
  required" screen that links to `store_url[platform]`.
- The **Forgot password?** link opens `password_reset_url` in the browser. AtomShop owns passwords.
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
    ]
  }
}
```

When `has_employer` is `true`, the **employer / business name** field is required. Show it
only in that case.

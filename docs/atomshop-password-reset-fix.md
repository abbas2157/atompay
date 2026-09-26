# Prompt: fix AtomShop's password reset and WhatsApp token

Paste everything below the line into Claude Code while working in `c:\xampp\htdocs\atomshop`.

---

## Task

Fix two security problems in AtomShop. They were found while building AtomPay, which shares
AtomShop's `users` table and passwords.

### 1. The password reset link lets anyone take over an account

`App\Http\Controllers\Auth\LoginController`:

- `send_email()` emails a link to `/password/reset/{uuid}`.
- `reset_password($id)` shows a "new password" form for whichever user owns that uuid.
- `change_password()` sets the new password for the `uuid` posted in the form, then **logs that user in**.

There is no token, no expiry and no one-time use. The uuid is the only secret, and it never
changes. Anyone who learns a user's uuid can set their password and sign in as them, at any
time and as often as they like. That includes an old email, a shared screenshot, a log, an API
response, or an admin screen. The form also posts the uuid back in plain HTML.

**Fix.** Replace it with a real reset:

- Laravel's built-in broker (`Password::sendResetLink()` / `Password::reset()` with the
  existing `password_reset_tokens` table) is the smallest correct change. Tokens are random,
  hashed, expire (60 min by default) and are single-use.
- Alternatively, reuse the OTP approach AtomPay uses (`PasswordResetService` in the atompay
  repo): a 6-digit code by email or WhatsApp (`WhatsAppTrait::send_otp` / `auth_otp`
  template), stored hashed, 10-minute expiry, 5 attempts.
- After a reset, rotate `remember_token`, revoke the user's Sanctum tokens, and don't respond
  differently for unknown emails (currently it says "Your Email is not registered", which
  reveals who has an account).
- Remove the `reset/{id}` route, or make it return 404, so old emailed links stop working.

### 2. The WhatsApp access token is hard-coded and committed

`config/website.php` has the Meta WhatsApp Cloud API `Token` as a literal string (and
`WhatsAppTrait::welcome_on_website()` has another one inline). Anyone with read access to the repo can
send WhatsApp messages as AtomShop's business number.

**Fix.**

1. In Meta Business Manager, **rotate** the token (a system-user token with only the
   `whatsapp_business_messaging` permission), because the old one has to be treated as leaked.
2. Change the config to `'Token' => env('WHATSAPP_TOKEN')` and put the new value in `.env` only.
   Do the same for `Phone_Number_ID` for consistency.
3. Remove the inline token from `welcome_on_website()` and use `config('website.whatsapp.Token')`.
4. Give AtomPay the same new values (`WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID` in its `.env`).
   AtomPay uses the number only for password-reset codes.

## Done when

- Opening `/password/reset/<any uuid>` no longer lets you change a password.
- A reset link or code expires and works once.
- `grep -rn "EAA" config app` finds no token.

# AtomPay Mobile — Product Requirements

**Owner:** Mudassar Abbas · **Last updated:** 2026-09-24 · **Status:** API complete for all phases (`docs/api/`); Flutter app not started

## 1. What AtomPay is

AtomPay is the buy-now-pay-in-instalments service behind **AtomShop.pk**, a Pakistani
e-commerce store. A customer is assessed once (identity, address, income) and gets a
**purchase limit**. They then pick AtomPay at AtomShop checkout and repay monthly.

The website (`atompay.shop`, this Laravel repo) already does all of this. The **mobile app** brings the
customer side to Android and iOS, where most Pakistani customers actually are.

> AtomPay does not sell products. Shopping happens on AtomShop. AtomPay is the
> payment method, the limit, and the repayment schedule.

## 2. Who it's for

| User | Needs | In the app? |
|---|---|---|
| **Customer** (AtomShop account, role `customer`) | Get a limit, see what's available, know what's due and when | **Yes — the only audience** |
| Staff (admin / amos / manager / recovery) | Verify addresses, decide limits | No. Staff stay on the web review queue |
| Sellers | — | No. They're refused at sign-in |

Typical customer: salaried or self-employed, mid-income, Android-first, often on mobile
data, comfortable with WhatsApp-style UIs and less so with long forms. English UI in v1,
with Urdu planned.

## 3. Goals

1. **Faster onboarding.** A customer completes KYC (CNIC photos + selfie from the phone camera)
   and the financial profile in one sitting, in under 5 minutes.
2. **"How much can I spend?" in one tap.** The limit card is the home screen.
3. **Fewer missed payments.** Next-due visibility and push reminders before each due date.
4. **One account.** The same email/phone and password as AtomShop. No second sign-up.

### Success metrics

| Metric | Target |
|---|---|
| Registration → KYC submitted | ≥ 60% within 7 days |
| KYC form completion time (median) | < 5 min |
| Instalments paid late (app users vs web-only) | 20% fewer |
| Crash-free sessions | ≥ 99.5% |

## 4. The core process

It mirrors the web app, and the server derives it via `ProcessTracker`:

```
KYC → Address verification → Income assessment → Risk assessment → Purchase limit → AtomShop purchase
(customer)   (staff visit)       (customer)          (system+staff)     (staff decides)    (on atomshop.pk)
```

Each stage is `done | current | upcoming | blocked`. The app renders the same stepper.

## 5. Scope by phase

> The API for **all four phases** is live (`docs/api/`). The phases below are the
> order the **app** gets built in.

### Phase 1 — Auth & Profile

- **Splash / launch:** Check the stored token with `GET /me`.
- **Sign in:** Email or phone + password. Show a clear message for staff/seller accounts.
- **Register:** Name, mobile, email, password. Creates an AtomShop customer account.
- **Profile / KYC Section 1:** Full name, CNIC, mobile, DOB, address, city, CNIC front and back
  photos, selfie. Prefilled from AtomShop. Status badge (not started / under review /
  verified / rejected). Warn before editing a verified profile.
- **Account screen:** Name, email, phone (read-only), sign out, sign out of all devices.
- **Forgot password:** Opens AtomShop's reset page in the browser (no native flow in v1).

### Phase 2 — Application & Dashboard

- **Financial profile (KYC Section 3):** Employment status, employer, income source, monthly
  income, existing instalments, monthly expenses. Creates a pending assessment.
- **Dashboard:** Limit card (approved, used, available, max instalment, tenure), process
  stepper, next due instalment, status banner (pending / approved / conditional / rejected).
- **Income estimator:** "What could I get?" preview (30% of income, instalment cap 10%),
  computed by the server.

### Phase 3 — Plans & Calculator

- **My plans:** Each active AtomShop order with its product, progress bar, schedule (paid /
  due / late / upcoming) and totals.
- **Calculator:** Price, down payment (20–60%) and tenure give the monthly amount. Uses a server quote.
- **Shop on AtomShop:** Deep link / browser hand-off to atomshop.pk.

### Phase 4 — Engagement

- Push notifications (FCM) for due in 3 days, due today, overdue, limit decided, and address
  visit scheduled.
- Urdu localisation (RTL).
- Biometric unlock (the fingerprint/face gates the stored token).

### Out of scope (all phases unless revisited)

- Paying instalments in the app. Payment is collected through AtomShop's existing channels.
  This needs a separate gateway decision.
- Browsing or buying products in the app (that's AtomShop's app/site).
- Staff/admin features.
- Editing the AtomShop account (name/email/phone/password). AtomShop owns those records.

## 6. Functional requirements (Phase 1)

| # | Requirement |
|---|---|
| F1 | A user can register with name, Pakistani mobile, email, password (≥ 8). Errors show per field. |
| F2 | A user can sign in with email **or** mobile in any common format. |
| F3 | Non-customer accounts are refused with *"Please sign in with an AtomShop customer account."* |
| F4 | The session persists for 30 days. After a `401` the app returns to sign-in without a crash or loop. |
| F5 | Sign out revokes the token server-side. "Sign out everywhere" revokes all tokens. |
| F6 | Profile form is pre-filled from `GET /profile`. CNIC and mobile are formatted as you type. |
| F7 | CNIC photos from camera or gallery. Selfie from the front camera. Images are compressed before upload (≤ 4 MB). |
| F8 | First submission needs all 3 images. Later edits may keep existing images. |
| F9 | Uploaded documents can be viewed (authenticated) but are never cached to disk. |
| F10 | A `429` shows a wait message using `Retry-After`. |

## 7. Non-functional requirements

- **Platforms:** Android 8+ (API 26+) first, iOS 14+.
- **Network:** Usable on 3G. Every request has a timeout and a retry affordance. Forms
  survive a failed submit without losing input.
- **Security:** Token in secure storage only. No CNIC, selfie, income or token in logs,
  analytics or crash reports. Screenshots blocked on KYC screens (Android `FLAG_SECURE`).
  TLS only in production.
- **Accessibility:** Text scales to 200% without clipping. Touch targets ≥ 48 dp. Contrast
  meets WCAG AA.
- **Size:** Release APK < 25 MB.

## 8. Open questions

1. **Password reset.** v1 links out to AtomShop's `/password/forgot` (URL comes from `/app-config`).
   Is a native OTP flow wanted later? It would need AtomShop's agreement, because it writes `users.password`.
2. **Register throttle** is 5/hour per IP. Pakistani carriers put many users behind one
   IP (CGNAT). This may need to be keyed differently for the app before launch.
3. **Paying in-app.** Is there a payment gateway (JazzCash / Easypaisa / card) to integrate, or
   do payments stay offline and through AtomShop?
4. ~~**Rejected KYC reason.**~~ *Resolved:* the website already shows staff notes to customers,
   and the app does the same (banner, decided assessment, KYC-rejected push).
5. **App store identity.** Package name / bundle id, developer account, and whether it ships
   under AtomShop's publisher account.

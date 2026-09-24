# Prompt: AtomPay verification inside the AtomShop admin panel

Paste everything below the line into Claude Code while working in `c:\xampp\htdocs\atomshop`.

---

## Task

Add an **"AtomPay"** section to the AtomShop admin dashboard so an admin can review and verify AtomPay customers without leaving AtomShop. Admin must be able to: see every AtomPay application, open one customer, look at their CNIC/selfie documents, record the physical address verification, and approve / conditionally approve / reject their purchase limit.

Read this whole brief first, then explore the existing admin code (`routes/dashboards/admin.php`, `app/Http/Controllers/Dashboards/Admin/Accounts/CustomerController.php`, `resources/views/dashboards/admin/v2/accounts/customers/`) so the new screens match the current admin v2 look and conventions exactly.

## Background: what an AtomPay customer is

AtomPay (`c:\xampp\htdocs\atompay`, Laravel 12) is the instalment / buy-now-pay-later companion site for AtomShop.pk. It **does not have its own users**. It signs people in against AtomShop's `users` table with the same email + password they use on the shop.

An **AtomPay customer** is therefore:

- a row in AtomShop's `users` table with `role = 'customer'` and `status = 'active'`,
- who has logged in to My AtomPay (`/my`) and submitted the AtomPay application form.

Submitting that form creates two kinds of AtomPay-owned rows in the **same `atomshop` database**:

| Table | What it holds | Rows per customer |
|---|---|---|
| `atompay_kyc_profiles` | Section 1 – identity (full name, CNIC, mobile, DOB, residential address, city, CNIC front/back scans, selfie). Section 2 – the one-time **physical address verification** recorded by staff. | exactly 1 (`user_id` is unique) |
| `atompay_credit_assessments` | Section 3 – income & financial profile the customer declared. Section 4 – system-computed risk score. Section 5 – the **limit decision** (approved limit, max instalment, tenure, status) recorded by staff. | 1 or more; the newest `approved`/`conditional` row is the live limit |

Life-cycle of an AtomPay customer:

```
AtomShop customer account
   └─ signs in to AtomPay, submits application
        ├─ atompay_kyc_profiles       verification_status = pending   ← staff verify address (Section 2)
        └─ atompay_credit_assessments status = pending                ← staff decide limit   (Sections 4–5)
                └─ status = approved | conditional  → limit in force, customer can pick AtomPay at AtomShop checkout
                └─ status = rejected               → no limit; customer may re-apply (creates a new assessment row)
```

"Used" limit = the customer's unpaid AtomShop `order_instalments` on Normal orders; "available" = approved_limit − used. AtomShop already owns the orders/instalments, so nothing new is needed there.

AtomShop's existing `customers.verified` flag and `customer_verifications` table are a separate, older verification. The AtomPay KYC is richer (CNIC scans, selfie, DOB, signed field form). Keep both; see "Sync decision" below.

## Ground rules (must follow)

1. **Same database, no new tables.** Both apps point at `DB_DATABASE=atomshop`. Read and write the existing `atompay_*` tables directly. Do not create migrations that alter them – AtomPay owns their schema and tracks its own migrations in `atompay_migrations`.
2. **Write only the staff columns.** Mirror what AtomPay's own staff area does:
   - On `atompay_kyc_profiles` staff may set only: `address_verified`, `face_verified`, `verified_at`, `verification_status`, `verification_notes`, `verification_form_path`, `verified_by`. Never touch the customer-submitted identity fields.
   - On `atompay_credit_assessments` staff may set only: `credit_history`, `approved_limit`, `max_instalment`, `approved_tenure`, `status`, `notes`, `decided_by`, `decided_at`. Never touch Section 3 income fields.
   - `verified_by` / `decided_by` = the admin's `users.id`.
3. **Enum values must match AtomPay exactly** (all lowercase strings):
   - `verification_status`: `pending | verified | rejected`
   - `credit_history`: `none | good | fair | poor`
   - assessment `status`: `pending | approved | conditional | rejected` (staff may never set it back to `pending`)
   - `employment_status`: `salaried | self_employed | business_owner | freelancer | retired | unemployed`
   - `income_source`: `salary | business | rental | remittance | pension | other`
   - `payment_history`: `none | on_time | some_late | defaulted`
   - `risk_category`: `low | medium | high`
4. **Documents live on a shared private disk that both apps point at.** The two apps share a database but not a project folder, and the DB stores only a *relative* path (`kyc/42/cnic_front-20260917101500.jpg`). AtomPay already defines this disk in its own `config/filesystems.php`; copy it verbatim so both roots resolve to the same directory and the file exists exactly once:
   ```php
   'atompay_kyc' => [
       'driver' => 'local',
       'root'   => env('ATOMPAY_KYC_ROOT', base_path('../atompay/storage/app/private')),
       'serve'  => false,
       'throw'  => true,
       'report' => false,
       'permissions' => [
           'file' => ['public' => 0644, 'private' => 0660],
           'dir'  => ['public' => 0755, 'private' => 0770],
       ],
   ],
   ```
   Set `ATOMPAY_KYC_ROOT` in `.env` / `.env.example` to **the same value AtomPay uses** — `/var/www/shared/atompay` in production, `C:\xampp\htdocs\atompay\storage\app\private` locally. If the two values ever differ, uploads silently vanish from the other app's view.

   The `base_path('../atompay/...')` fallback above is a local-development convenience only: it resolves correctly under XAMPP, where both projects sit side by side in `htdocs`. In production AtomShop lives at `/var/www/html` and AtomPay at `/var/www/atompay.shop`, so they are *not* siblings and the fallback would point at nothing — the env var must be set explicitly there. See `atompay/docs/shared-storage.md` for the server setup.

   Stream files through an admin-only controller with `Cache-Control: private, no-store`; never expose them via a public URL, `storage:link`, or an entry in `filesystems.links`. Uploaded signed verification forms go to the **same** disk under `kyc/{user_id}/verification_form-{YmdHis}.{ext}` — the exact convention AtomPay's `KycService::replaceDocument()` uses — and the previous file is deleted when replaced. Getting the filename convention wrong produces orphans neither app can find.

   Port AtomPay's `atompay:kyc-check` command (`app/Console/Commands/KycStorageCheck.php`) as `atomshop:kyc-check`. Run it in both apps after deploying: identical output proves a document written by one is readable by the other.
5. **CNIC** is stored as 13 bare digits. Display as `#####-#######-#`.
6. **Auth**: everything sits inside the existing `EnsureUserIsAdmin` group in `routes/dashboards/admin.php` under prefix `admin/atompay`, route names `admin.atompay.*`.
7. Match the existing admin v2 Blade layout, tables, badges, filters, pagination (`config('app.per_page')`) and flash-message patterns. Do not introduce a new CSS framework or JS library.
8. Money is PKR integers; format with the helper the admin already uses.

## Models to add (in `app/Models/Atompay/`)

- `KycProfile` – `$table = 'atompay_kyc_profiles'`; casts `date_of_birth`, `verified_at` → date, `submitted_at` → datetime, `address_verified`, `face_verified` → boolean. Relations: `user()`, `city()`, `verifier()` (`verified_by`). Accessor `cnic_formatted`.
- `CreditAssessment` – `$table = 'atompay_credit_assessments'`; integer casts on all money columns, `decided_at` → datetime. Relations: `user()`, `decider()` (`decided_by`). Scopes: `pending()`, `usable()` (approved + conditional).
- Add to `App\Models\User`: `atompayKycProfile()` hasOne, `atompayAssessments()` hasMany latest-first, `atompayActiveAssessment()` hasOne-of-many (newest usable by `decided_at` then `id`).
- Put the enum value lists in `app/Enums/Atompay/*.php` (string-backed PHP enums) so the selects and validation share one source.

## Routes

```
GET  admin/atompay                               → dashboard cards: pending KYC, pending decisions, approved, rejected, total limit in force
GET  admin/atompay/applications                  → list (filter: status, verification_status, q = name/phone/email/CNIC)
GET  admin/atompay/applications/{assessment}     → review page for one application
POST admin/atompay/applications/{assessment}/verify-address   → Section 2
POST admin/atompay/applications/{assessment}/decide           → Sections 4–5
GET  admin/atompay/documents/{profile}/{document}             → stream cnic-front | cnic-back | selfie | form
GET  admin/atompay/customers/{user}              → all of one customer's assessments + KYC (link from existing customers list)
```

## Screens

### 1. Applications list
Columns: #, customer (name, phone, email), CNIC, submitted at, KYC status badge, income, provisional limit, risk badge (score + category), decision status badge, action "Review". Tabs/filters for `pending | approved | conditional | rejected` with counts, plus a "KYC pending" quick filter. Default to pending, newest first.

### 2. Review page (the core screen) – five sections, same order as the AtomPay form

**Section 1 – Identity (read-only)**: full name, CNIC (formatted), mobile, DOB + age, residential address, city, submitted at. Three document thumbnails (CNIC front, CNIC back, selfie) opening full-size in a modal/new tab via the document route. Beside it, show what AtomShop already knows for cross-checking: `users.name/phone/email`, `customers.cnic_no/address/city/area/verified`, and the old `customer_verifications` row if any. Highlight mismatches (name, CNIC, phone, city) in amber.

**Section 2 – Address verification (form)**: fields `address_verified` (yes/no), `face_verified` (yes/no – selfie matches CNIC), `verified_at` (date, ≤ today), `verification_status` (pending/verified/rejected), `verification_notes` (≤ 2000), `verification_form` (jpg/jpeg/png/webp/pdf ≤ 4096 KB, optional; show link to current file if one exists). Show who verified and when if already done. Validate with a FormRequest.

**Section 3 – Financial profile (read-only)**: employment status, employer, income source, monthly income, existing instalments (other lenders), monthly expenses, disposable income.

**Section 4 – Risk (read-only + one input)**: risk score /100 with category badge, payment history label, existing AtomShop obligations (PKR), plus a live "AtomShop repayment record" panel computed here: count of instalments, paid late, unpaid overdue, total unpaid, next due date — from `order_instalments` where `user_id` = customer and order_type = Normal. The one editable field is `credit_history` (none/good/fair/poor) – staff's view of the customer's wider credit record.

**Section 5 – Decision (form)**: `approved_limit` (int ≥ 0), `max_instalment` (int ≥ 0), `approved_tenure` (months, 1–60, nullable), `status` (approved/conditional/rejected), `notes` (≤ 2000). Pre-fill with the provisional figures already on the row. Show a reference line: "Policy: limit = 30% of income (PKR X), instalment ≤ 10% of income (PKR Y)" so the admin sees how far they are deviating. Confirm dialog before submit. After saving set `decided_by`, `decided_at = now()` and redirect to the list with a flash message.

Side panel: history of this customer's other assessments (date, status, limit, decided by) and a link to the AtomShop customer edit page.

### 3. Customer detail (`admin/atompay/customers/{user}`)
KYC summary + a table of every assessment. Also add a small "AtomPay" column/badge on the existing `admin.customers.index` list (none / pending / approved PKR X / rejected) linking here.

## Sync decision (implement, but make it a checkbox)

On the Section 2 form add a checkbox "Also mark AtomShop customer as verified", checked by default when `verification_status = verified`. When ticked, set `customers.verified = 1` for that user and clear `not_verified_reason`. Do not create/alter `customer_verifications` rows – that remains the old field-agent workflow. Never un-verify the AtomShop customer from this screen.

## Risk score note

`risk_score`, `risk_category`, `payment_history`, `existing_obligations` are computed by AtomPay's `RiskScoringService` when the customer submits, and re-computed by AtomPay's own staff screen at decision time. **Do not port that service in this task.** Display the stored values as "provisional (scored by AtomPay on {updated_at})", let the admin set `credit_history`, and leave the score columns untouched. Add a TODO comment pointing to `atompay/app/Services/RiskScoringService.php` if re-scoring inside AtomShop is wanted later.

## Navigation

Add an "AtomPay" group to the admin sidebar in `resources/views/dashboards/admin/layout/header.blade.php` with links: Overview, Applications (with a pending-count badge). Active-state logic must follow the existing `request()->segment()` pattern.

## Verification checklist (run these before reporting done)

1. Log in as `admin@atomshop.com` → `/admin/atompay` renders with correct counts (compare against `SELECT status, COUNT(*) FROM atompay_credit_assessments GROUP BY status`).
2. Open a pending application: all five sections render, documents stream (200, `Content-Disposition` inline, no-store header), a non-admin gets 403 on the document route, an unauthenticated user is redirected.
3. Submit Section 2 with a PDF form → row updated, file appears under `atompay/storage/app/private/kyc/{user_id}/`, old form deleted, `verified_by` = admin id, AtomShop `customers.verified` flips to 1 when the checkbox is on.
4. Submit Section 5 as approved with limit 45000 / instalment 15000 / tenure 12 → row updated, `decided_at` set, list moves it to the Approved tab.
5. Open AtomPay at `http://localhost/atompay/my` as that customer → the limit card shows PKR 45,000 and "verified" KYC. (This proves the write is compatible with AtomPay.)
6. Attempt `status = pending` in Section 5 → validation error.
7. Run `vendor/bin/pint --dirty` and existing tests; add feature tests for the three POST endpoints and the document authorisation.

Report what you built, every file touched, and the results of each checklist item.

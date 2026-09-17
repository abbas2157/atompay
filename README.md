# AtomPay


Instalment-payment companion site for [AtomShop.pk](https://atomshop.pk). Explains the product, estimates a plan, records a customer's income for a credit limit, and shows the signed-in customer their limit and payment schedule.

**Stack:** Laravel 12 · Blade (server-rendered, SEO-first) · Tailwind 4 · Alpine.js · Vite. **Database:** shares AtomShop's MySQL database.

## Setup (XAMPP)

```bash
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate        # creates only atompay_* tables in the atomshop DB
php artisan test
```

Open <http://localhost/atompay>. The root `index.php` + `.htaccess` are a dev-only shim so the app runs from an `htdocs` subfolder (same as `atomshop`); production should point the web root at `public/`.

`.env` keys that matter:

| Key | Purpose |
|---|---|
| `DB_DATABASE=atomshop` | Shared database. AtomPay tracks its own migrations in `atompay_migrations`, so `migrate` / `rollback` never touch AtomShop's history. |
| `ATOMSHOP_URL` | Storefront links (hero CTA, product URLs). |
| `ATOMPAY_ASSET_URL` | Where AtomShop serves product pictures from. |
| `ASSET_URL` | Dev shim only - points at `public/` for Vite assets. |

## How the shared database is used

| AtomShop table | AtomPay model | Access |
|---|---|---|
| `users` | `User` | Read + create (registration mirrors what AtomShop checkout creates). Auth uses the same bcrypt password. |
| `customers`, `customer_verifications` | `Customer`, `CustomerVerification` | Read |
| `orders`, `carts`, `products` | `Order`, `Cart`, `Product` | Read |
| `order_instalments` | `OrderInstalment` | Read - drives the payment schedule and "in use" figure |
| `installment_calculators` | `InstallmentCalculator` | Read - tenures + per-month % (cached 10 min) |
| `atompay_kyc_profiles` | `KycProfile` | **Owned by AtomPay** - sections 1-2 of the KYC form |
| `atompay_credit_assessments` | `CreditAssessment` | **Owned by AtomPay** - sections 3-5: financial profile, risk, decision |

Models on AtomShop tables use the `BelongsToAtomShop` trait, which guards every attribute so a stray mass-assignment can't rewrite shop data.

## KYC & purchase limit assessment

The five-section form maps onto two AtomPay-owned tables and three roles:

| Section | Who | Where |
|---|---|---|
| 1. Customer verification (name, CNIC, mobile, DOB, address, CNIC + selfie uploads) | Customer | `atompay_kyc_profiles` via `/my/application` |
| 2. One-time address verification (physical visit, signed form, verified by/at, status) | Staff | `atompay_kyc_profiles` via `/staff/assessments/{id}` |
| 3. Income & financial profile (employment, employer, income, source, existing instalments, expenses, disposable) | Customer | `atompay_credit_assessments` via `/my/application` |
| 4. Risk assessment (credit history, payment history, obligations, score, category) | System provisional, staff confirm | `RiskScoringService` + `/staff/assessments/{id}` |
| 5. Limit decision (approved limit, max instalment, tenure, approved/conditional/rejected) | Staff | `CreditAssessmentService::decide()` |

**Process:** KYC → Address Verification → Income Assessment → Risk Assessment → Purchase Limit → AtomShop Purchase. `ProcessTracker` derives the stage the customer is at purely from those two rows and the dashboard renders it as `<x-process-steps>`.

- Only an assessment with status `approved` or `conditional` is a spendable limit (`User::activeAssessment`). A new submission is always a new pending row and never overwrites the limit in force.
- KYC documents are stored on the private `local` disk under `kyc/{user_id}/` and served only through `/documents/{profile}/{doc}` to the owner or staff.
- Staff = AtomShop users with a role in `config('atompay.staff_roles')`; they sign in at the same `/login` and land on `/staff/assessments`.
- Risk score starts at 100 and loses points per signal (late AtomShop instalments, defaults, obligations/income ratio, disposable income below the 10% cap, unverified KYC, staff credit-history view); every penalty and band is in `config('atompay.risk')`.

## Business rules (all in `config/atompay.php`)

- **Plan pricing** mirrors AtomShop's checkout (`Web\Order\OrderController::checkout_perform`):
  `markup = per_month% × months × (price − advance)`, down payment 20–60% of price. Implemented once in `InstalmentQuoteService`; the Alpine calculator mirrors it client-side and `POST /quote` exposes it server-side. `tests/Unit/InstalmentQuoteServiceTest` pins the numbers.
- **Credit limit** = 30% of monthly income; **max instalment** = min(10% of income, disposable income) (`CreditAssessmentService::provisionalLimit`). The client only ever submits its profile; limits are computed server-side and stay *pending* until staff decide.

## Layout

```
app/
  Http/Controllers/Web       HomeController, QuoteController, AssessmentController, SitemapController
  Http/Controllers/Auth      AuthController (login/register/logout against AtomShop users)
  Http/Controllers/Account   DashboardController, ApplicationController, DocumentController
  Http/Controllers/Staff     AssessmentController (queue, review, address verification, decision)
  Http/Requests              LoginRequest, RegisterRequest, AssessmentRequest, QuoteRequest,
                             KycApplicationRequest, AddressVerificationRequest, LimitDecisionRequest
  Http/Middleware            EnsureUserIsCustomer (customer), EnsureUserIsStaff (staff)
  Services                   InstalmentQuoteService, CreditAssessmentService, KycService,
                             RiskScoringService, ObligationService, PaymentScheduleService, ProcessTracker
  DataTransferObjects        InstalmentQuote, RiskProfile
  Support                    Money  (@pkr Blade directive)
  Models                     + Enums/ (OrderStatus, InstalmentStatus, AssessmentStatus)
resources/views/
  components/layouts         base (head/assets) → app (marketing) / account (signed-in)
  components                 seo, brand-mark, section-head, faq-list, input, alert, status-pill, side-row
  home/, pages/, auth/, account/, staff/, forms/, seo/
```

## SEO

- `<x-seo>` emits title, description, canonical, robots, Open Graph, Twitter, and JSON-LD on every page.
- Home carries `Organization`, `HowTo` and `FAQPage` schema; `/faq` carries `FAQPage`.
- `/sitemap.xml` and `public/robots.txt` list public pages only; auth and account pages are `noindex`.
- Everything is server-rendered; Alpine only enhances the two calculators.

## Still to build

- Enforce the approved limit at AtomShop checkout.
- Password reset (AtomShop's flow can be linked instead).

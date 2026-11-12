<?php

/*
|--------------------------------------------------------------------------
| AtomPay business rules
|--------------------------------------------------------------------------
| Every number the product copy promises lives here, so the calculator,
| the credit assessment, the dashboard and the marketing text can never
| disagree. The installment math itself mirrors Atomshop's checkout
| (Web\Order\OrderController::checkout_perform) — keep them in sync.
*/

return [

    // The storefront this service is the payment option for.
    'shop_url' => env('ATOMSHOP_URL', 'https://atomshop.pk'),

    // AtomShop writes its timestamps in its own APP_TIMEZONE; AtomPay runs in UTC. See App\Support\ShopTime.
    'shop_timezone' => env('ATOMSHOP_TIMEZONE', 'Asia/Karachi'),

    /*
    | Where AtomShop serves uploaded product pictures from. products.picture
    | holds a path like "images/products/foo.webp", and the shop serves those
    | under /public:
    |
    |   https://atomshop.pk/public/images/products/foo.webp   200
    |   https://atomshop.pk/images/products/foo.webp          404
    |
    | Derived from ATOMSHOP_URL, so the shop's host is configured in exactly one
    | place and cannot drift from the product links built off shop_url. Set
    | ATOMPAY_ASSET_URL only when the pictures are somewhere other than
    | <shop>/public - in development, say, where the shop is on localhost.
    |
    | It must be ABSOLUTE and point at the SHOP. A relative value such as
    | "/public", or an absolute one naming AtomPay, resolves against
    | atompay.shop and 404s every picture on the site.
    */
    'asset_url' => env('ATOMPAY_ASSET_URL')
        ?: rtrim(env('ATOMSHOP_URL', 'https://atomshop.pk'), '/').'/public',

    /*
    | The Atom ecosystem's apps, shown under each card in the ecosystem section.
    | Mirrors Atomshop's config('website.app.*') - keep the two in step; the shop
    | is the authority, because its /apps page and api/app/config serve the same
    | links. AtomPay's own pair is not repeated here: it lives in api.store_url,
    | which the mobile API already reads, so each link has one home.
    |
    | AtomBrand has no Android build published, so its android link is null and
    | the card says "Android soon" rather than linking nowhere.
    */
    'apps' => [
        'atomshop' => [
            'android' => env('ATOMSHOP_APP_ANDROID') ?: 'https://play.google.com/store/apps/details?id=com.ecommerce.atomshop',
            'ios' => env('ATOMSHOP_APP_IOS') ?: 'https://apps.apple.com/us/app/atomshop/id6744655747',
        ],
        'atomseller' => [
            'android' => env('ATOMSELLER_APP_ANDROID') ?: 'https://play.google.com/store/apps/details?id=com.ecommerce.atomseller',
            'ios' => env('ATOMSELLER_APP_IOS') ?: 'https://apps.apple.com/us/app/atom-seller-sell-more/id6817232001',
        ],
        'atombrand' => [
            'android' => env('ATOMBRAND_APP_ANDROID'),
            'ios' => env('ATOMBRAND_APP_IOS') ?: 'https://apps.apple.com/us/app/atombrand-brand-partner/id6820037360',
        ],
    ],

    // "Buy with AtomPay" in the app (docs/api/orders.md). Atomshop places the
    // order through POST {atomshop_api}/atompay/orders; server_key must equal
    // Atomshop's ATOMPAY_SERVER_KEY. Off (app-config features.in_app_orders
    // false, POST /orders 409) until both are set.
    'orders' => [
        'enabled' => (bool) env('ATOMPAY_IN_APP_ORDERS', false),
        'atomshop_api' => env('ATOMSHOP_API_URL', 'https://atomshop.pk/api'),
        'server_key' => (string) env('ATOMPAY_SERVER_KEY', ''),
        'timeout' => (int) env('ATOMSHOP_API_TIMEOUT', 60),
    ],

    'credit' => [
        // Approved limit as a share of declared monthly income.
        'limit_ratio' => 0.30,
        // Cap on any single monthly instalment as a share of income.
        'instalment_ratio' => 0.10,
    ],

    /*
    | AtomPay's own plan at Atomshop checkout. Fixed by the owner's decision of
    | 2026-10-24 and enforced server-side by Atomshop: AtomPay is offered on
    | eligible listed products only (products.atompay_eligible or
    | brands.atompay_eligible), and is ALWAYS three months, no advance and 0%
    | markup. A 3% processing fee is shown with every plan as a disclaimer and
    | is never added to an order total; delivery charges apply.
    |
    | This mirrors AtomShop's Website\InstallmentPlanService::atompayPlan() and
    | config('website.atompay.processing_fee_percent'). Keep the two in step.
    |
    | The `plan` block below is a DIFFERENT product - Atomshop seller financing,
    | 3-12 months with a 20-60% advance and a markup per month. The v1 design's
    | calculator quotes that one; the v2 design quotes this one.
    */
    'atompay_plan' => [
        'months' => 3,
        'advance' => 0,
        'markup_percent' => 0,
        'processing_fee_percent' => env('ATOMPAY_PROCESSING_FEE_PERCENT', 3),
        // The worked example the v2 landing page prices.
        'example_price' => 100000,
        'example_income' => 150000,
    ],

    'plan' => [
        // Down payment must fall inside this share of the product price.
        'min_advance_ratio' => 0.20,
        'max_advance_ratio' => 0.60,
        // Fallbacks when the installment_calculators row is missing.
        'default_tenures' => [3, 6, 9, 12],
        'default_per_month' => 4.0,
        // Calculator config is read from AtomShop's table; cache it briefly.
        'cache_ttl' => 600,
        // Calculator slider bounds on the landing page.
        'price_min' => 10000,
        'price_max' => 300000,
        'price_step' => 5000,
    ],

    // Mobile app (routes/api.php).
    'api' => [
        // A mobile sign-in stays valid this long; then the app must sign in again.
        'token_ttl_days' => 30,
        // Token name when the app does not send a device_name.
        'default_device_name' => 'AtomPay app',
        // Builds older than this are told to update (GET /app-config). Semver.
        'min_version' => [
            'android' => env('ATOMPAY_APP_MIN_ANDROID', '1.0.0'),
            'ios' => env('ATOMPAY_APP_MIN_IOS', '1.0.0'),
        ],
        /*
        | The published AtomPay apps. Both stores carry it (Atomshop's
        | config('website.app.atompay') holds the same two links - keep them in
        | step). Defaults are the real listings so the site links correctly with
        | nothing in .env; override only to point at a different build.
        */
        'store_url' => [
            'android' => env('ATOMPAY_APP_STORE_ANDROID')
                ?: 'https://play.google.com/store/apps/details?id=com.ecommerce.atompay',
            'ios' => env('ATOMPAY_APP_STORE_IOS')
                ?: 'https://apps.apple.com/us/app/atompay-pay-in-steps/id6817231756',
        ],
        // Store-listing links. Privacy falls back to this site's /privacy page when unset.
        'links' => [
            'privacy_url' => env('ATOMPAY_PRIVACY_URL'),
            'terms_url' => env('ATOMPAY_TERMS_URL'),
            'account_deletion_url' => env('ATOMPAY_ACCOUNT_DELETION_URL'),
        ],
        'support' => [
            'phone' => env('ATOMPAY_SUPPORT_PHONE'),
            'whatsapp' => env('ATOMPAY_SUPPORT_WHATSAPP'),
            'email' => env('ATOMPAY_SUPPORT_EMAIL'),
        ],
        /*
        | Planned downtime. While `active`, GET /app-config tells the app to
        | show its Maintenance screen. `php artisan down` (a 503 on every
        | route) has the same effect, without a message or a time.
        */
        'maintenance' => [
            'active' => (bool) env('ATOMPAY_APP_MAINTENANCE', false),
            'message' => env('ATOMPAY_APP_MAINTENANCE_MESSAGE'),
            'until' => env('ATOMPAY_APP_MAINTENANCE_UNTIL'),   // ISO-8601, e.g. 2026-11-10T16:30:00+05:00
        ],
        // Ask Atom by voice. Off hides the mic in the app (no new build needed).
        'voice' => (bool) env('ATOMPAY_APP_VOICE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | The company behind AtomPay
    |--------------------------------------------------------------------------
    | Atomshop sells on instalments itself (merchant credit): there is no
    | separate lender and no licence. These facts appear on the Key Fact
    | Statement, /privacy and GET /app-config `business`.
    |
    | Atomshop's admin panel is the source (shared `atom_settings` table, keys
    | `atompay.<name>`, read by App\Services\CompanySettings); the values here
    | are only the fallback for a key the admin has left empty. Anything
    | still unset is null - the app shows a placeholder rather than a guess.
    | The old ATOMPAY_LENDER_* names are still read so existing .env files
    | keep working.
    */
    'company' => [
        'company_name' => env('ATOMPAY_COMPANY_NAME') ?: env('ATOMPAY_LENDER_NAME'),
        'registered_address' => env('ATOMPAY_COMPANY_ADDRESS') ?: env('ATOMPAY_LENDER_ADDRESS'),
        // Where the servers are, e.g. "Pakistan (Karachi)". The app shows "Your data is stored in ...".
        'data_hosting' => env('ATOMPAY_DATA_LOCATION'),
        'late_payment' => env('ATOMPAY_KFS_LATE_PAYMENT'),
        'early_settlement' => env('ATOMPAY_KFS_EARLY_SETTLEMENT'),
        // e.g. "2 working days"; the KFS complaints line adds "We reply within ...".
        'complaint_response_time' => env('ATOMPAY_COMPLAINT_RESPONSE_TIME'),
    ],
    /*
    | Facts the privacy policy states that only the business can confirm. Each
    | line appears on /privacy once its value is set; `atompay:deploy-check`
    | lists the ones still missing. The company name and data hosting are in
    | `company` above.
    */
    'privacy' => [
        // How long financial and identity records are kept after an account closes or is deleted.
        'record_retention_years' => env('ATOMPAY_RECORD_RETENTION_YEARS'),
        // Whether a credit bureau (e.g. SBP's eCIB or a private bureau) is checked or reported to.
        'credit_bureau' => env('ATOMPAY_CREDIT_BUREAU'),
    ],

    /*
    | Fixed sign-ins for Apple and Google app review, (re)made by
    | `php artisan atompay:seed-review-accounts`. Credentials live only in
    | .env and the store submission notes - never in the repository.
    | A: full account (KYC verified, limit, an order being repaid, inbox).
    | B: verified with a limit and no orders, so deleting it succeeds.
    */
    'review_accounts' => [
        'a' => ['email' => env('ATOMPAY_REVIEW_A_EMAIL'), 'password' => env('ATOMPAY_REVIEW_A_PASSWORD')],
        'b' => ['email' => env('ATOMPAY_REVIEW_B_EMAIL'), 'password' => env('ATOMPAY_REVIEW_B_PASSWORD')],
    ],

    // In-app complaints (POST /api/v1/complaints). Value => label, in display order.
    'complaints' => [
        'topics' => [
            'payment' => 'A payment',
            'order' => 'An order',
            'limit' => 'My limit or application',
            'account' => 'My account or sign-in',
            'other' => 'Something else',
        ],
        'min_length' => 10,
        'max_length' => 2000,
    ],

    /*
    | Customer notifications: an in-app inbox (atompay_notifications) plus a
    | push to every registered device. `php artisan atompay:notify` sweeps
    | for anything worth telling a customer - it runs every 10 minutes and
    | is idempotent, so decisions made in Atomshop's admin are caught too.
    */
    'notifications' => [
        // Instalment reminders: days before the due date, and days overdue.
        'remind_before_days' => [3, 0],
        'remind_overdue_days' => [1, 7],
        // Reminders only go out in these local hours - never at 2am.
        'timezone' => 'Asia/Karachi',
        'quiet_before' => 9,
        'quiet_after' => 21,
        // How far back the sweep looks for decisions / verifications.
        'lookback_days' => 3,
    ],

    // Only these AtomShop accounts may sign in to My AtomPay.
    'customer_role' => 'customer',

    // AtomShop roles allowed into the AtomPay staff review area.
    'staff_roles' => ['admin', 'amos', 'manager', 'recovery'],

    /*
    | Forgot password: a one-time code by email (email typed) or WhatsApp
    | (mobile typed), then a short-lived reset token. See PasswordResetService.
    */
    'password_reset' => [
        'code_length' => 6,
        'code_ttl_minutes' => 10,
        'max_attempts' => 5,       // wrong codes before the code is burnt
        'resend_seconds' => 60,    // a new code is not sent sooner than this
        'hourly_limit' => 5,       // codes actually sent per account per hour
        'token_ttl_minutes' => 15, // time to choose a new password after the code
    ],

    /*
    | Sign-up with ONE contact: an email gets its code by email, a mobile
    | gets it on WhatsApp. The account is only created once that code is
    | proven. See SignupService.
    */
    'signup' => [
        'ttl_minutes' => 30,          // the whole pending sign-up
        'code_ttl_minutes' => 10,     // each code
        'max_attempts' => 5,          // verify calls per sign-up
        'resend_seconds' => 60,
        'hourly_limit' => 3,          // codes per phone number / email address per hour
        /*
        | Atomshop's users.email is required and unique, so a mobile-only
        | account is given "<03XXXXXXXXX>@<this domain>". AtomPay never mails
        | these (User::hasRealEmail). Use a domain you own with no mailbox, so
        | anything Atomshop sends there bounces instead of reaching a stranger.
        */
        'placeholder_email_domain' => env('ATOMPAY_PLACEHOLDER_EMAIL_DOMAIN', 'no-email.atompay.shop'),
    ],

    'kyc' => [
        // Shared private disk + folder for CNIC, selfie and signed verification
        // forms. AtomShop's admin panel points its own disk at the same root,
        // so both apps read and write one copy of each document.
        'disk' => 'kyc',
        'path' => 'kyc',
        'max_upload_kb' => 4096,
        'min_age' => 18,

        // Every upload passes UploadScanner's structural checks. With a clamd
        // socket here it is also virus-scanned:
        //   unix:///var/run/clamav/clamd.ctl  or  tcp://127.0.0.1:3310
        // Empty = structural checks only (deploy-check warns).
        'scan' => [
            'clamav' => env('ATOMPAY_CLAMAV_SOCKET'),
            'timeout' => 30,
            // clamd configured but unreachable: refuse the upload (true) or
            // accept it on the structural checks alone (false).
            'fail_closed' => (bool) env('ATOMPAY_CLAMAV_FAIL_CLOSED', true),
        ],
    ],

    /*
    | Risk score starts at 100 and loses points for each signal below; the
    | bands turn the score into low / medium / high. Provisional scores are
    | computed by RiskScoringService and confirmed (or overridden) by staff.
    */
    'risk' => [
        'bands' => ['low' => 70, 'medium' => 45],
        'penalties' => [
            'late_instalment' => 8,   // per late Atomshop installment, up to the cap
            'late_instalment_cap' => 40,
            'defaulted' => 30,  // any installment overdue longer than default_days
            'obligations_ratio' => 25,  // scaled by obligations / monthly income (max 1)
            'low_disposable' => 20,  // disposable income below the max installment
            'no_payment_history' => 5,
            'kyc_not_verified' => 10,
            'credit_history' => ['none' => 5, 'good' => 0, 'fair' => 10, 'poor' => 30],
        ],
        'default_days' => 60,
    ],

    'faqs' => [
        [
            'q' => 'Do I buy directly on atompay.shop?',
            'a' => 'No. You shop and choose products on Atomshop.pk. AtomPay is the payment method you select there — this site explains how it works, lets you estimate a plan, and lets you check your approved limit before you shop.',
        ],
        [
            'q' => 'Is every product on Atomshop eligible?',
            'a' => 'Only products marked as installment-eligible on their Atomshop listing can be paid for with AtomPay. Eligibility is set per product.',
        ],
        [
            'q' => 'What decides my monthly payment?',
            'a' => 'The product price, your down payment, and the term length you choose. The calculator gives a close estimate; the exact figures are confirmed at checkout.',
        ],
        [
            'q' => "What happens after I'm approved?",
            'a' => 'Your Atomshop order proceeds as normal and ships to you. You then make your down payment and repay the rest in the monthly installments agreed at checkout.',
        ],
    ],

    'steps' => [
        ['title' => 'Shop on Atomshop.pk',        'text' => 'Pick any product marked eligible for installments.'],
        ['title' => 'Choose AtomPay at checkout', 'text' => 'Select AtomPay instead of paying the full amount in cash.'],
        ['title' => 'Get a quick approval',       'text' => 'We confirm your details and set your monthly plan.'],
        ['title' => 'Pay monthly, keep the product', 'text' => 'Your order ships right away; you repay over the chosen term.'],
    ],
];

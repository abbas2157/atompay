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

    /*
    | Where AtomShop serves uploaded product pictures from. It MUST be absolute
    | and MUST end in /public - products.picture holds a path like
    | "images/products/foo.webp", and the shop serves those under /public:
    |
    |   https://atomshop.pk/public/images/products/foo.webp   200
    |   https://atomshop.pk/images/products/foo.webp          404
    |
    | A relative value such as "/public" resolves against AtomPay's own host,
    | so every picture 404s from atompay.shop instead of loading from the shop.
    */
    'asset_url' => env('ATOMPAY_ASSET_URL', 'https://atomshop.pk/public'),

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

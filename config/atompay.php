<?php

/*
|--------------------------------------------------------------------------
| AtomPay business rules
|--------------------------------------------------------------------------
| Every number the product copy promises lives here, so the calculator,
| the credit assessment, the dashboard and the marketing text can never
| disagree. The instalment math itself mirrors AtomShop's checkout
| (Web\Order\OrderController::checkout_perform) — keep them in sync.
*/

return [

    // The storefront this service is the payment option for.
    'shop_url'  => env('ATOMSHOP_URL', 'https://atomshop.pk'),

    // Where AtomShop serves uploaded product pictures from.
    'asset_url' => env('ATOMPAY_ASSET_URL', 'https://atomshop.pk'),

    'credit' => [
        // Approved limit as a share of declared monthly income.
        'limit_ratio'      => 0.30,
        // Cap on any single monthly instalment as a share of income.
        'instalment_ratio' => 0.10,
    ],

    'plan' => [
        // Down payment must fall inside this share of the product price.
        'min_advance_ratio' => 0.20,
        'max_advance_ratio' => 0.60,
        // Fallbacks when the installment_calculators row is missing.
        'default_tenures'   => [3, 6, 9, 12],
        'default_per_month' => 4.0,
        // Calculator config is read from AtomShop's table; cache it briefly.
        'cache_ttl'         => 600,
        // Calculator slider bounds on the landing page.
        'price_min'  => 10000,
        'price_max'  => 300000,
        'price_step' => 5000,
    ],

    // Only these AtomShop accounts may sign in to My AtomPay.
    'customer_role' => 'customer',

    // AtomShop roles allowed into the AtomPay staff review area.
    'staff_roles' => ['admin', 'amos', 'manager', 'recovery'],

    'kyc' => [
        // Private disk + folder for CNIC, selfie and signed verification forms.
        'disk'          => 'local',
        'path'          => 'kyc',
        'max_upload_kb' => 4096,
        'min_age'       => 18,
    ],

    /*
    | Risk score starts at 100 and loses points for each signal below; the
    | bands turn the score into low / medium / high. Provisional scores are
    | computed by RiskScoringService and confirmed (or overridden) by staff.
    */
    'risk' => [
        'bands' => ['low' => 70, 'medium' => 45],
        'penalties' => [
            'late_instalment'     => 8,   // per late AtomShop instalment, up to the cap
            'late_instalment_cap' => 40,
            'defaulted'           => 30,  // any instalment overdue longer than default_days
            'obligations_ratio'   => 25,  // scaled by obligations / monthly income (max 1)
            'low_disposable'      => 20,  // disposable income below the max instalment
            'no_payment_history'  => 5,
            'kyc_not_verified'    => 10,
            'credit_history'      => ['none' => 5, 'good' => 0, 'fair' => 10, 'poor' => 30],
        ],
        'default_days' => 60,
    ],

    'faqs' => [
        [
            'q' => 'Do I buy directly on atompay.shop?',
            'a' => 'No. You shop and choose products on AtomShop.pk. AtomPay is the payment method you select there — this site explains how it works, lets you estimate a plan, and lets you check your approved limit before you shop.',
        ],
        [
            'q' => 'Is every product on AtomShop eligible?',
            'a' => 'Only products marked as instalment-eligible on their AtomShop listing can be paid for with AtomPay. Eligibility is set per product.',
        ],
        [
            'q' => 'What decides my monthly payment?',
            'a' => 'The product price, your down payment, and the term length you choose. The calculator gives a close estimate; the exact figures are confirmed at checkout.',
        ],
        [
            'q' => "What happens after I'm approved?",
            'a' => 'Your AtomShop order proceeds as normal and ships to you. You then make your down payment and repay the rest in the monthly instalments agreed at checkout.',
        ],
    ],

    'steps' => [
        ['title' => 'Shop on AtomShop.pk',        'text' => 'Pick any product marked eligible for instalments.'],
        ['title' => 'Choose AtomPay at checkout', 'text' => 'Select AtomPay instead of paying the full amount in cash.'],
        ['title' => 'Get a quick approval',       'text' => 'We confirm your details and set your monthly plan.'],
        ['title' => 'Pay monthly, keep the product', 'text' => 'Your order ships right away; you repay over the chosen term.'],
    ],
];

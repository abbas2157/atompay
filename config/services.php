<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Firebase Cloud Messaging (HTTP v1) for mobile push. Point this at the
    | service-account JSON downloaded from Firebase console > Project settings
    | > Service accounts. Unset = push is skipped (the in-app inbox still
    | fills), which is what local development and tests use.
    */
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
    ],

    /*
    | Google Analytics 4 on the website (resources/views/components/analytics).
    | Empty = no tag and no Google hosts in the CSP. Outside production hits
    | are sent with debug_mode so they land in GA's DebugView.
    */
    'google_analytics' => [
        'measurement_id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    /*
    | WhatsApp Cloud API (Meta) - AtomShop's business number, used by AtomPay
    | ONLY for password-reset codes. Same account, number and approved
    | `auth_otp` template as AtomShop's WhatsAppTrait::send_otp(); copy the
    | token and phone number id from AtomShop's WhatsApp settings.
    | Unset outside production = the code is written to the log instead.
    */
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v19.0'),
        'otp_template' => env('WHATSAPP_OTP_TEMPLATE', 'auth_otp'),
        'otp_language' => env('WHATSAPP_OTP_LANGUAGE', 'en_US'),
    ],

];

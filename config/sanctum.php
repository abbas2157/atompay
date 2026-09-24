<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
|--------------------------------------------------------------------------
| Sanctum - bearer tokens for the AtomPay mobile app
|--------------------------------------------------------------------------
| The API is token-only. There is no SPA sharing the web session, so:
|
|   stateful []  no domain gets cookie-based API access
|   guard    []  a web session never authenticates an /api request; only
|                an `Authorization: Bearer` token does
|
| Tokens live in `atompay_personal_access_tokens`, NOT AtomShop's
| `personal_access_tokens` (see App\Models\PersonalAccessToken): both apps
| name their user model App\Models\User, so a shared table would let every
| AtomShop token open AtomPay and the other way round.
|
| Expiry is set per token when it is issued (config('atompay.api')), so
| `expiration` stays null here.
*/

return [

    'stateful' => [],

    'guard' => [],

    'expiration' => null,

    // Makes a leaked token recognisable to GitHub / GitGuardian secret scanning.
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'atompay_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];

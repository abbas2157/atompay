<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

/**
 * AtomPay-owned. Mobile-app bearer tokens, kept apart from AtomShop's
 * `personal_access_tokens` so a token issued by one app never opens the
 * other. Registered in AppServiceProvider via Sanctum::usePersonalAccessTokenModel().
 */
class PersonalAccessToken extends SanctumToken
{
    protected $table = 'atompay_personal_access_tokens';
}

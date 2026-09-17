<?php

namespace App\Models\Enums;

enum VerificationStatus: string
{
    use HasOptions;

    case Pending  = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}

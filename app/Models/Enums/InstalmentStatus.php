<?php

namespace App\Models\Enums;

enum InstalmentStatus: string
{
    case Paid   = 'Paid';
    case Unpaid = 'Unpaid';
}

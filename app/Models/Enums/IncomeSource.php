<?php

namespace App\Models\Enums;

enum IncomeSource: string
{
    use HasOptions;

    case Salary     = 'salary';
    case Business   = 'business';
    case Rental     = 'rental';
    case Remittance = 'remittance';
    case Pension    = 'pension';
    case Other      = 'other';
}

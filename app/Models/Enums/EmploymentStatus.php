<?php

namespace App\Models\Enums;

enum EmploymentStatus: string
{
    use HasOptions;

    case Salaried      = 'salaried';
    case SelfEmployed  = 'self_employed';
    case BusinessOwner = 'business_owner';
    case Freelancer    = 'freelancer';
    case Retired       = 'retired';
    case Unemployed    = 'unemployed';

    public function label(): string
    {
        return match ($this) {
            self::SelfEmployed  => 'Self-employed',
            self::BusinessOwner => 'Business owner',
            default             => ucfirst($this->value),
        };
    }

    /** Whether an employer / business name makes sense for this status. */
    public function hasEmployer(): bool
    {
        return in_array($this, [self::Salaried, self::SelfEmployed, self::BusinessOwner], true);
    }
}

<?php

namespace App\Models\Enums;

enum AssessmentStatus: string
{
    use HasOptions;

    case Pending     = 'pending';
    case Approved    = 'approved';
    case Conditional = 'conditional';
    case Rejected    = 'rejected';

    /** Statuses under which the limit may be spent. */
    public static function usable(): array
    {
        return [self::Approved, self::Conditional];
    }

    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
}

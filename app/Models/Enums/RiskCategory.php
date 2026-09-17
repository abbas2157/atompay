<?php

namespace App\Models\Enums;

enum RiskCategory: string
{
    use HasOptions;

    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';

    public static function fromScore(int $score): self
    {
        $bands = config('atompay.risk.bands');

        return match (true) {
            $score >= $bands['low']    => self::Low,
            $score >= $bands['medium'] => self::Medium,
            default                    => self::High,
        };
    }
}

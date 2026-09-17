<?php

namespace App\DataTransferObjects;

use App\Models\Enums\PaymentHistory;
use App\Models\Enums\RiskCategory;

/** Output of RiskScoringService::assess() - the Section 4 figures plus why. */
final readonly class RiskProfile
{
    /** @param string[] $reasons human-readable penalties applied, for the reviewer */
    public function __construct(
        public int $score,
        public RiskCategory $category,
        public PaymentHistory $paymentHistory,
        public int $existingObligations,
        public array $reasons,
    ) {}

    /** Columns to write onto a CreditAssessment. */
    public function toAttributes(): array
    {
        return [
            'risk_score'           => $this->score,
            'risk_category'        => $this->category,
            'payment_history'      => $this->paymentHistory,
            'existing_obligations' => $this->existingObligations,
        ];
    }
}

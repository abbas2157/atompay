<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CreditAssessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A credit assessment as the customer may see it: what they declared, and
 * the decision once there is one. The provisional limit computed on
 * submission is withheld until staff decide (the web dashboard does the
 * same), and the risk score / credit-history view stay internal.
 *
 * @mixin CreditAssessment
 */
class CreditAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $decided = $this->status->isDecided();

        return [
            'id' => $this->id,
            'status' => $this->status->value,               // pending | approved | conditional | rejected
            'status_label' => $this->status->label(),
            'is_usable' => $this->isUsable(),

            // Section 3 - as declared
            'employment_status' => $this->employment_status?->value,
            'employment_status_label' => $this->employment_status?->label(),
            'employer_name' => $this->employer_name,
            'income_source' => $this->income_source?->value,
            'income_source_label' => $this->income_source?->label(),
            'monthly_income' => $this->monthly_income,
            'existing_instalments' => $this->existing_instalments,
            'monthly_expenses' => $this->monthly_expenses,
            'disposable_income' => $this->disposable_income,

            // Section 5 - only once decided
            'approved_limit' => $decided ? $this->approved_limit : null,
            'max_instalment' => $decided ? $this->max_instalment : null,
            'approved_tenure' => $decided ? $this->approved_tenure : null,
            'notes' => $decided ? $this->notes : null,

            'submitted_at' => $this->created_at?->toIso8601String(),
            'decided_at' => $this->decided_at?->toIso8601String(),
        ];
    }
}

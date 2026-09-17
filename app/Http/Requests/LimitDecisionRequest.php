<?php

namespace App\Http\Requests;

use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\CreditHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sections 4-5 - the reviewer's credit-history view and the limit decision. */
class LimitDecisionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'credit_history'  => ['required', Rule::enum(CreditHistory::class)],
            'approved_limit'  => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_instalment'  => ['required', 'integer', 'min:0', 'max:100000000'],
            'approved_tenure' => ['nullable', 'integer', 'min:1', 'max:60'],
            'status'          => ['required', Rule::enum(AssessmentStatus::class), Rule::notIn([AssessmentStatus::Pending->value])],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function decision(): array
    {
        return $this->validated();
    }
}

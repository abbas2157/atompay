<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesFinancialProfile;
use App\Http\Requests\Concerns\ValidatesKycIdentity;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The customer-facing application: Section 1 (identity + documents) and
 * Section 3 (income & financial profile) in one submission.
 */
class KycApplicationRequest extends FormRequest
{
    use ValidatesFinancialProfile, ValidatesKycIdentity;

    public function rules(): array
    {
        return [
            // Section 1 - customer verification
            ...$this->identityRules(),

            // Section 3 - income & financial profile
            ...$this->financialRules(),
        ];
    }

    public function messages(): array
    {
        return [...$this->identityMessages(), ...$this->financialMessages()];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeIdentity();
    }
}

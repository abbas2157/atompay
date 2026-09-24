<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesKycIdentity;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Section 1 of the KYC form on its own, as the app submits it from the
 * profile screen. The financial profile (Section 3) is a separate step.
 */
class KycProfileRequest extends FormRequest
{
    use ValidatesKycIdentity;

    public function rules(): array
    {
        return $this->identityRules();
    }

    public function messages(): array
    {
        return $this->identityMessages();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeIdentity();
    }
}

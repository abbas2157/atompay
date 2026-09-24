<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesFinancialProfile;
use Illuminate\Foundation\Http\FormRequest;

/** Section 3 of the KYC form on its own - the app submits identity separately (POST /profile). */
class ApplicationRequest extends FormRequest
{
    use ValidatesFinancialProfile;

    public function rules(): array
    {
        return $this->financialRules();
    }

    public function messages(): array
    {
        return $this->financialMessages();
    }
}

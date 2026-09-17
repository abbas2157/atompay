<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'monthly_income' => ['required', 'integer', 'min:1000', 'max:100000000'],
        ];
    }

    public function income(): int
    {
        return (int) $this->validated('monthly_income');
    }
}

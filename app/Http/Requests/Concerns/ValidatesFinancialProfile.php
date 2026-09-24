<?php

namespace App\Http\Requests\Concerns;

use App\Models\Enums\EmploymentStatus;
use App\Models\Enums\IncomeSource;
use Illuminate\Validation\Rule;

/**
 * Section 3 of the KYC form (income & financial profile). Shared by the
 * web application form and the mobile API so both accept exactly the same thing.
 */
trait ValidatesFinancialProfile
{
    protected function financialRules(): array
    {
        return [
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'employer_name' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => EmploymentStatus::tryFrom((string) $this->employment_status)?->hasEmployer() ?? false)],
            'income_source' => ['required', Rule::enum(IncomeSource::class)],
            'monthly_income' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'existing_instalments' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'monthly_expenses' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    protected function financialMessages(): array
    {
        return [
            'employer_name.required' => 'Please tell us your employer or business name.',
        ];
    }

    /** Section 3 fields, with the optional amounts defaulted to zero. */
    public function financialProfile(): array
    {
        $data = $this->safe()->only(['employment_status', 'employer_name', 'income_source', 'monthly_income', 'existing_instalments', 'monthly_expenses']);

        return [
            ...$data,
            'existing_instalments' => (int) ($data['existing_instalments'] ?? 0),
            'monthly_expenses' => (int) ($data['monthly_expenses'] ?? 0),
        ];
    }
}

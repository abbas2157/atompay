<?php

namespace App\Http\Requests;

use App\Models\Enums\EmploymentStatus;
use App\Models\Enums\IncomeSource;
use App\Models\KycProfile;
use App\Rules\Cnic;
use App\Rules\PakistanMobile;
use App\Support\Pakistan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * The customer-facing application: Section 1 (identity + documents) and
 * Section 3 (income & financial profile) in one submission.
 */
class KycApplicationRequest extends FormRequest
{
    public function rules(): array
    {
        $maxKb = config('atompay.kyc.max_upload_kb');
        $hasDocs = $this->user()->kycProfile?->hasDocuments() ?? false;
        $image = ['image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"];

        return [
            // Section 1 - customer verification
            'full_name' => ['required', 'string', 'max:255'],
            'cnic' => ['required', new Cnic, Rule::unique('atompay_kyc_profiles', 'cnic')->ignore($this->user()->id, 'user_id')],
            'mobile' => ['required', new PakistanMobile],
            'date_of_birth' => ['required', 'date', 'before:-'.config('atompay.kyc.min_age').' years'],
            'residential_address' => ['required', 'string', 'max:1000'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'cnic_front' => [$hasDocs ? 'nullable' : 'required', ...$image],
            'cnic_back' => [$hasDocs ? 'nullable' : 'required', ...$image],
            'selfie' => [$hasDocs ? 'nullable' : 'required', ...$image],

            // Section 3 - income & financial profile
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'employer_name' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => EmploymentStatus::tryFrom((string) $this->employment_status)?->hasEmployer() ?? false)],
            'income_source' => ['required', Rule::enum(IncomeSource::class)],
            'monthly_income' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'existing_instalments' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'monthly_expenses' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'You must be at least '.config('atompay.kyc.min_age').' to apply.',
            'employer_name.required' => 'Please tell us your employer or business name.',
        ];
    }

    protected function prepareForValidation(): void
    {
        /*
         * Store the canonical form, whatever the customer typed: 13 bare
         * digits for the CNIC, 03XXXXXXXXX for the mobile. Two people writing
         * the same number differently must not become two different records,
         * and the `unique` rule above only works if they match exactly.
         *
         * An unreadable mobile falls through as the raw input so the rule can
         * explain the problem instead of reporting an empty field.
         */
        $this->merge([
            'cnic' => Pakistan::normalizeCnic($this->cnic),
            'mobile' => Pakistan::normalizeMobile($this->mobile) ?: trim((string) $this->mobile),
        ]);
    }

    /** Section 1 fields (no files). */
    public function identity(): array
    {
        return $this->safe()->only(['full_name', 'cnic', 'mobile', 'date_of_birth', 'residential_address', 'city_id']);
    }

    /** @return array<string, UploadedFile> */
    public function documents(): array
    {
        return array_filter(array_map(fn ($input) => $this->file($input), array_combine(
            array_keys(KycProfile::DOCUMENTS), array_keys(KycProfile::DOCUMENTS),
        )));
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

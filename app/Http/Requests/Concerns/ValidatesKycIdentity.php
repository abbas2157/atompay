<?php

namespace App\Http\Requests\Concerns;

use App\Models\KycProfile;
use App\Rules\Cnic;
use App\Rules\PakistanMobile;
use App\Support\Pakistan;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Section 1 of the KYC form (identity + documents). Shared by the web
 * application form and the mobile API so both accept exactly the same thing.
 */
trait ValidatesKycIdentity
{
    protected function identityRules(): array
    {
        $maxKb = config('atompay.kyc.max_upload_kb');
        $hasDocs = $this->user()->kycProfile?->hasDocuments() ?? false;
        $image = ['image', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"];

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'cnic' => ['required', new Cnic, Rule::unique('atompay_kyc_profiles', 'cnic')->ignore($this->user()->id, 'user_id')],
            'mobile' => ['required', new PakistanMobile],
            'date_of_birth' => ['required', 'date', 'before:-'.config('atompay.kyc.min_age').' years'],
            'residential_address' => ['required', 'string', 'max:1000'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'cnic_front' => [$hasDocs ? 'nullable' : 'required', ...$image],
            'cnic_back' => [$hasDocs ? 'nullable' : 'required', ...$image],
            'selfie' => [$hasDocs ? 'nullable' : 'required', ...$image],
        ];
    }

    protected function identityMessages(): array
    {
        return [
            'date_of_birth.before' => 'You must be at least '.config('atompay.kyc.min_age').' to apply.',
        ];
    }

    /**
     * Store the canonical form, whatever the customer typed: 13 bare
     * digits for the CNIC, 03XXXXXXXXX for the mobile. Two people writing
     * the same number differently must not become two different records,
     * and the `unique` rule above only works if they match exactly.
     *
     * An unreadable mobile falls through as the raw input so the rule can
     * explain the problem instead of reporting an empty field.
     */
    protected function normalizeIdentity(): void
    {
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
}

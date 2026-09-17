<?php

namespace App\Http\Requests;

use App\Models\Enums\VerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Section 2 - recorded by the field agent after the physical visit. */
class AddressVerificationRequest extends FormRequest
{
    public function rules(): array
    {
        $maxKb = config('atompay.kyc.max_upload_kb');

        return [
            'address_verified'    => ['required', 'boolean'],
            'face_verified'       => ['required', 'boolean'],
            'verified_at'         => ['required', 'date', 'before_or_equal:today'],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'verification_notes'  => ['nullable', 'string', 'max:2000'],
            'verification_form'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxKb}"],
        ];
    }

    public function findings(): array
    {
        return $this->safe()->only(['address_verified', 'face_verified', 'verified_at', 'verification_status', 'verification_notes']);
    }
}

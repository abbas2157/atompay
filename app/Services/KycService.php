<?php

namespace App\Services;

use App\Models\Enums\VerificationStatus;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Section 1 (customer-submitted identity) and Section 2 (staff-recorded
 * address verification) of the KYC form. Documents go to a private disk;
 * DocumentController streams them back only to the owner or staff.
 */
class KycService
{
    /**
     * The customer's profile, or a new one pre-filled from what AtomShop
     * already knows (users.name/phone, customers.cnic_no/address).
     */
    public function profileFor(User $user): KycProfile
    {
        $profile = $user->kycProfile;
        if ($profile) {
            return $profile;
        }

        $customer = $user->customer;

        return new KycProfile([
            'full_name'           => $user->name,
            'mobile'              => $user->phone,
            'cnic'                => $customer?->cnic_no ? preg_replace('/\D/', '', $customer->cnic_no) : null,
            'residential_address' => $customer?->address,
            'city_id'             => $customer?->city_id,
        ]);
    }

    /**
     * @param array<string, mixed>        $data   validated section-1 fields
     * @param array<string, UploadedFile> $files  keyed like KycProfile::DOCUMENTS
     */
    public function submit(User $user, array $data, array $files): KycProfile
    {
        $profile = $user->kycProfile ?? new KycProfile(['user_id' => $user->id]);

        foreach (KycProfile::DOCUMENTS as $input => $column) {
            if (isset($files[$input])) {
                $this->replaceDocument($profile, $column, $files[$input], $user);
            }
        }

        $profile->fill($data);
        $profile->submitted_at = now();

        // Any change to identity details re-opens verification.
        if ($profile->isDirty(['full_name', 'cnic', 'date_of_birth', 'residential_address', ...array_values(KycProfile::DOCUMENTS)])) {
            $profile->verification_status = VerificationStatus::Pending;
            $profile->face_verified       = false;
        }

        $profile->save();

        return $profile;
    }

    /** Section 2 - the field agent's findings. */
    public function recordAddressVerification(KycProfile $profile, User $staff, array $data, ?UploadedFile $signedForm): KycProfile
    {
        if ($signedForm) {
            $this->replaceDocument($profile, 'verification_form_path', $signedForm, $profile->user);
        }

        $profile->fill($data);
        $profile->verified_by = $staff->id;
        $profile->verified_at = $data['verified_at'] ?? today();
        $profile->save();

        return $profile;
    }

    private function replaceDocument(KycProfile $profile, string $column, UploadedFile $file, User $owner): void
    {
        $disk = Storage::disk(config('atompay.kyc.disk'));

        if ($old = $profile->{$column}) {
            $disk->delete($old);
        }

        $name = str_replace('_path', '', $column).'-'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension();
        $profile->{$column} = $file->storeAs(config('atompay.kyc.path').'/'.$owner->id, $name, config('atompay.kyc.disk'));
    }
}

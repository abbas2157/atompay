<?php

namespace App\Http\Resources\Api\V1;

use App\Models\KycProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Section 1 of the KYC form plus the outcome of Section 2 (address
 * verification). Before the first submission this is an unsaved profile
 * pre-filled from AtomShop (KycService::profileFor), with status
 * `not_started`. Staff notes and face-match flags stay internal.
 *
 * @mixin KycProfile
 */
class KycProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => self::statusOf($this->resource),
            'full_name' => $this->full_name,
            'cnic' => $this->cnic,
            'cnic_formatted' => $this->cnic ? $this->cnic_formatted : null,
            'mobile' => $this->mobile,
            'mobile_formatted' => $this->mobile ? $this->mobile_formatted : null,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'residential_address' => $this->residential_address,
            'city' => $this->city_id && $this->city ? new CityResource($this->city) : null,
            'documents' => $this->documents(),
            'address_verified' => (bool) $this->address_verified,
            'verified_at' => $this->verified_at?->toDateString(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }

    /** One word the app can switch on. A profile row only exists once it has been submitted. */
    public static function statusOf(?KycProfile $profile): string
    {
        return $profile?->exists ? $profile->verification_status->value : 'not_started';
    }

    /** @return array<string, array{uploaded: bool, url: string|null}> */
    private function documents(): array
    {
        $documents = [];
        foreach (KycProfile::DOCUMENTS as $name => $column) {
            $uploaded = filled($this->{$column});
            $documents[$name] = [
                'uploaded' => $uploaded,
                'url' => $uploaded ? route('api.v1.profile.documents', $name) : null,
            ];
        }

        return $documents;
    }
}

<?php

namespace App\Services;

use App\Models\CreditAssessment;
use App\Models\Enums\AssessmentStatus;
use App\Models\KycProfile;

/**
 * The core process as the customer sees it:
 * KYC → Address Verification → Income Assessment → Risk Assessment → Purchase Limit → AtomShop Purchase
 *
 * Each stage is done | current | upcoming | blocked, derived purely from
 * the KYC profile and latest assessment - no separate state to keep in sync.
 */
class ProcessTracker
{
    /** @return array<int, array{key: string, title: string, hint: string, state: string}> */
    public function stages(?KycProfile $kyc, ?CreditAssessment $assessment): array
    {
        $kycDone      = $kyc?->isSubmitted() ?? false;
        $addressDone  = $kyc?->isVerified() ?? false;
        $addressFail  = $kyc && $kyc->verification_status->value === 'rejected';
        $incomeDone   = $assessment !== null;
        $decided      = $assessment?->status->isDecided() ?? false;
        $usable       = $assessment?->isUsable() ?? false;
        $rejected     = $assessment?->status === AssessmentStatus::Rejected;

        $stages = [
            ['key' => 'kyc',     'title' => 'KYC',                  'hint' => 'Identity details and CNIC uploaded',         'done' => $kycDone,     'blocked' => false],
            ['key' => 'address', 'title' => 'Address verification', 'hint' => 'One-time physical visit by our team',         'done' => $addressDone, 'blocked' => $addressFail],
            ['key' => 'income',  'title' => 'Income assessment',    'hint' => 'Your financial profile',                      'done' => $incomeDone,  'blocked' => false],
            ['key' => 'risk',    'title' => 'Risk assessment',      'hint' => 'Reviewed by AtomPay',                         'done' => $decided,     'blocked' => $rejected],
            ['key' => 'limit',   'title' => 'Purchase limit',       'hint' => 'Approved limit, instalment cap and tenure',   'done' => $usable,      'blocked' => $rejected],
            ['key' => 'shop',    'title' => 'AtomShop purchase',    'hint' => 'Choose AtomPay at checkout',                  'done' => false,        'blocked' => $rejected],
        ];

        $currentFound = false;
        foreach ($stages as &$s) {
            $s['state'] = match (true) {
                $s['blocked']  => 'blocked',
                $s['done']     => 'done',
                ! $currentFound => 'current',
                default        => 'upcoming',
            };
            if ($s['state'] === 'current' || $s['state'] === 'blocked') {
                $currentFound = true;
            }
            unset($s['done'], $s['blocked']);
        }

        return $stages;
    }
}

<?php

namespace App\Services;

use App\Models\CreditAssessment;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\VerificationStatus;
use App\Models\KycProfile;

/**
 * The one line that tells a customer where they are in the process and
 * what happens next. Shared by the web dashboard banner and the mobile
 * dashboard, so both always say the same thing.
 *
 * Staff notes are shown on purpose: a rejection or condition is explained
 * in the reviewer's own words when they wrote any.
 */
class StatusBanner
{
    /**
     * @return array{tone: string, title: string, text: string, cta: string, action: string}
     *         tone: pending | blocked | done; action: what the call-to-action opens.
     */
    public function for(?KycProfile $profile, ?CreditAssessment $latest): array
    {
        return match (true) {
            $profile === null => [
                'tone' => 'pending', 'title' => 'Start your AtomPay application',
                'text' => 'Verify your identity and tell us about your income - it takes about five minutes.',
                'cta' => 'Apply now', 'action' => 'apply',
            ],
            $latest?->status === AssessmentStatus::Rejected => [
                'tone' => 'blocked', 'title' => 'Application not approved',
                'text' => $latest->notes ?: 'We could not approve a limit this time. You can re-apply if your circumstances change.',
                'cta' => 'Re-apply', 'action' => 'application',
            ],
            $profile->verification_status === VerificationStatus::Rejected => [
                'tone' => 'blocked', 'title' => 'Verification unsuccessful',
                'text' => $profile->verification_notes ?: 'We could not verify your details. Please review and resubmit your application.',
                'cta' => 'Update details', 'action' => 'profile',
            ],
            $latest?->isUsable() ?? false => [
                'tone' => 'done', 'title' => $latest->status === AssessmentStatus::Conditional ? 'Limit approved with conditions' : 'Verification complete',
                'text' => $latest->notes ?: 'Your limit below is confirmed and ready to use at AtomShop checkout.',
                'cta' => 'Improve my limit', 'action' => 'application',
            ],
            $latest === null => [
                'tone' => 'pending', 'title' => 'Tell us about your income',
                'text' => 'Your identity details are in. Add your income and expenses so we can work out your limit.',
                'cta' => 'Add income', 'action' => 'application',
            ],
            ! $profile->isVerified() => [
                'tone' => 'pending', 'title' => 'Address verification pending',
                'text' => 'Our representative will visit the address you gave to verify it and take your signature.',
                'cta' => 'Update details', 'action' => 'profile',
            ],
            default => [
                'tone' => 'pending', 'title' => 'Risk assessment in progress',
                'text' => 'Your address is verified. We are reviewing your financial profile and will confirm your limit shortly.',
                'cta' => 'Update income', 'action' => 'application',
            ],
        };
    }
}

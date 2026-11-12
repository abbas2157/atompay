<?php

namespace App\Services;

use App\Models\KycAccessLog;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records who looked at a customer's identity documents or CNIC number.
 *
 * Called before the document is sent, and not wrapped in a try/catch: if the
 * record cannot be written the request fails, so there is never a view that
 * left no trace.
 *
 * Only Atompay's own pages are covered. AtomShop's admin panel reads the same
 * shared documents and needs its own log.
 */
class KycAccessRecorder
{
    public function record(User $viewer, KycProfile $profile, string $item, Request $request): void
    {
        // The customer looking at their own documents is not an access to audit.
        if ($viewer->id === $profile->user_id) {
            return;
        }

        KycAccessLog::create([
            'viewer_id' => $viewer->id,
            'subject_user_id' => $profile->user_id,
            'kyc_profile_id' => $profile->id,
            'item' => $item,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use App\Services\KycAccessRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KYC documents live on a private disk. This is the only way to see one:
 * the owning customer, or staff, and only through a known column name.
 */
class DocumentController extends Controller
{
    private const COLUMNS = [
        'cnic-front' => 'cnic_front_path',
        'cnic-back'  => 'cnic_back_path',
        'selfie'     => 'selfie_path',
        'form'       => 'verification_form_path',
    ];

    public function __invoke(Request $request, KycProfile $profile, string $document, KycAccessRecorder $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $user->id === $profile->user_id, 403);

        $column = self::COLUMNS[$document] ?? abort(404);
        $path   = $profile->{$column} ?? abort(404);
        $disk   = Storage::disk(config('atompay.kyc.disk'));
        abort_unless($disk->exists($path), 404);

        // Staff views are logged before the file goes out (customers' own are not).
        $audit->record($user, $profile, $document, $request);

        return $disk->response($path, basename($path), ['Cache-Control' => 'private, no-store']);
    }
}

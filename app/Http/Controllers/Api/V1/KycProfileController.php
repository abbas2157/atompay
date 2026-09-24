<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\KycProfileRequest;
use App\Http\Resources\Api\V1\KycProfileResource;
use App\Models\KycProfile;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Section 1 of the KYC form (identity + documents) for the signed-in
 * customer. Same KycService as the website, so any change to identity
 * details re-opens address verification exactly as it does there.
 */
class KycProfileController extends Controller
{
    public function __construct(private readonly KycService $kyc) {}

    /** The saved profile, or a new one pre-filled from AtomShop. */
    public function show(Request $request): KycProfileResource
    {
        return new KycProfileResource($this->kyc->profileFor($request->user())->load('city'));
    }

    /** Multipart POST: fields + cnic_front / cnic_back / selfie images. */
    public function store(KycProfileRequest $request): JsonResponse
    {
        $profile = DB::transaction(fn () => $this->kyc->submit($request->user(), $request->identity(), $request->documents()));

        return (new KycProfileResource($profile->load('city')))
            ->response()
            ->setStatusCode($profile->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * One of the customer's own documents. There is no profile id in the
     * URL, so there is nothing to change to reach someone else's CNIC.
     */
    public function document(Request $request, string $document): StreamedResponse
    {
        $column = KycProfile::DOCUMENTS[$document] ?? abort(404);
        $path = $request->user()->kycProfile?->{$column} ?? abort(404);
        $disk = Storage::disk(config('atompay.kyc.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), ['Cache-Control' => 'private, no-store']);
    }
}

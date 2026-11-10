<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\KeyFactStatementMail;
use App\Services\KeyFactStatementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/** The Key Fact Statement of one of the customer's AtomPay orders, and a copy by email. */
class KeyFactStatementController extends Controller
{
    public function __construct(private readonly KeyFactStatementService $kfs) {}

    public function show(Request $request, int $order): JsonResponse
    {
        $kfs = $this->kfs->forOrder($request->user(), $order) ?? abort(404);

        return response()->json(['data' => $kfs]);
    }

    /**
     * Email a copy to the address on the account. Email is the only channel
     * today; SMS is not available, so `channel` exists for when it is.
     */
    public function send(Request $request, int $order): Response|JsonResponse
    {
        $request->validate(['channel' => ['required', Rule::in(['email'])]]);

        $user = $request->user();
        $kfs = $this->kfs->forOrder($user, $order) ?? abort(404);

        if (! $user->hasRealEmail()) {
            return response()->json([
                'message' => 'Your account has no email address. Add one on Atomshop.pk, or take a screenshot of this page.',
                'code' => 'no_email',
            ], 409);
        }

        try {
            Mail::to($user->email, $user->name)->send(new KeyFactStatementMail($user->shortName(), $kfs));
        } catch (Throwable $e) {
            Log::error('AtomPay email failed', ['mail' => KeyFactStatementMail::class, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            abort(503, 'We could not send the email just now. Please try again in a few minutes.');
        }

        return response()->noContent(202);
    }
}

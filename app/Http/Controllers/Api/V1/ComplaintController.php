<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ComplaintResource;
use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * In-app complaints: the grievance route SECP asks digital lenders for.
 * Each one gets a reference the customer can quote; a person replies.
 */
class ComplaintController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ComplaintResource::collection(
            Complaint::query()->where('user_id', $request->user()->id)->with('order')->latest('id')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'topic' => ['required', Rule::in(array_keys(config('atompay.complaints.topics')))],
            // Only one of the customer's own orders: someone else's id is "not found", not a hint.
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('user_id', $user->id)],
            'message' => ['required', 'string', 'min:'.config('atompay.complaints.min_length'), 'max:'.config('atompay.complaints.max_length')],
        ], [
            'order_id.exists' => 'Choose one of your own orders.',
            'message.min' => 'Tell us a little more, so we can look into it.',
        ]);

        $complaint = DB::transaction(function () use ($user, $data) {
            $complaint = Complaint::create([
                'user_id' => $user->id,
                'topic' => $data['topic'],
                'order_id' => $data['order_id'] ?? null,
                'message' => trim($data['message']),
                'status' => 'open',
            ]);
            $complaint->forceFill(['reference' => Complaint::referenceFor($complaint->id)])->save();

            return $complaint;
        });

        $this->tellSupport($complaint);

        return (new ComplaintResource($complaint->load('order')))->response()->setStatusCode(201);
    }

    /** Best effort: the complaint is saved whether or not this email goes. */
    private function tellSupport(Complaint $complaint): void
    {
        $to = config('atompay.api.support.email');

        if (! $to) {
            return;
        }

        try {
            Mail::raw(
                "New AtomPay complaint {$complaint->reference}\n"
                ."Topic: ".config('atompay.complaints.topics')[$complaint->topic]."\n"
                .'Order: '.($complaint->order_id ? Order::query()->find($complaint->order_id)?->reference : '-')."\n"
                ."Customer id: {$complaint->user_id}\n\n"
                .$complaint->message,
                fn ($m) => $m->to($to)->subject("AtomPay complaint {$complaint->reference}"),
            );
        } catch (Throwable $e) {
            Log::error('AtomPay complaint notification failed', ['complaint_id' => $complaint->id, 'error' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AskAtomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ask Atom for the app. Rules-based, like the website: answers come from
 * AtomPay's own rules and figures, no AI and nothing sent anywhere else.
 * Questions are not stored. Voice questions arrive here as text - the app
 * transcribes on the phone and never uploads audio.
 */
class AskAtomController extends Controller
{
    public function __construct(private readonly AskAtomService $atom) {}

    public function chips(): JsonResponse
    {
        return response()->json(['data' => $this->atom->chips()]);
    }

    public function ask(Request $request): JsonResponse
    {
        $question = $request->validate(['question' => ['required', 'string', 'max:300']])['question'];

        // Optional sign-in: with a valid token, answers link to the signed-in
        // screens (my application, my orders) instead of sign-up.
        if ($request->user('sanctum') instanceof User) {
            auth()->shouldUse('sanctum');
        }

        return response()->json(['data' => $this->atom->answerForApp($question)]);
    }
}

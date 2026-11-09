<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AskAtomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ask Atom: one question in, one answer out.
 *
 * Rules only - no model, no key, nothing sent anywhere - so a question never
 * leaves the server. The length cap and the throttle in routes/web.php are what
 * keep it cheap to leave open to the public.
 */
class AskAtomController extends Controller
{
    public function __invoke(Request $request, AskAtomService $atom): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:300'],
        ]);

        return response()->json($atom->answer($data['question']));
    }
}

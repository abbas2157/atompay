<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /** The signed-in account - what the app loads on launch to confirm its token. */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('kycProfile'));
    }
}

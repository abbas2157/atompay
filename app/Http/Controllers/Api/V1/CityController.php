<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CityResource;
use App\Models\City;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CityController extends Controller
{
    /** AtomShop's active cities, for the profile's city picker. */
    public function index(): AnonymousResourceCollection
    {
        return CityResource::collection(
            City::query()->where('status', 'active')->orderBy('title')->get(['id', 'title']),
        );
    }
}

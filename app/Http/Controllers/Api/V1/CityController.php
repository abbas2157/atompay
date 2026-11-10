<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CityResource;
use App\Models\Area;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CityController extends Controller
{
    /** AtomShop's active cities, for the profile's city picker. */
    public function index(): AnonymousResourceCollection
    {
        return CityResource::collection(
            City::query()->where('status', 'active')->orderBy('title')->get(['id', 'title']),
        );
    }

    /**
     * The active areas of one active city, for the area picker. Same list as
     * the website's /my/areas/{city}; POST /profile only accepts an area_id
     * from the city_id sent with it.
     */
    public function areas(Request $request): JsonResponse
    {
        $cityId = (int) $request->validate([
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('status', 'active')],
        ])['city_id'];

        $areas = Area::query()
            ->where('city_id', $cityId)
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'title']);

        return response()->json([
            'data' => $areas->map(fn (Area $area) => ['id' => $area->id, 'title' => $area->title])->values(),
        ]);
    }
}

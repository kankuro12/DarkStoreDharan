<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    /**
     * List all active cities with delivery ETA and fee (§3, §29).
     */
    public function index(): JsonResponse
    {
        $cities = City::active()
            ->with(['deliveryZones' => fn ($q) => $q->active()])
            ->get()
            ->map(fn ($city) => [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'province' => $city->province,
                'cod_enabled' => $city->cod_enabled,
                'prepaid_enabled' => $city->prepaid_enabled,
                'default_delivery_fee' => (float) $city->default_delivery_fee,
                'free_delivery_minimum' => (float) $city->free_delivery_minimum,
                'estimated_delivery_minutes' => $city->estimated_delivery_minutes,
                'zones' => $city->deliveryZones->map(fn ($zone) => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'delivery_fee' => (float) $zone->delivery_fee,
                    'estimated_minutes' => $zone->estimated_minutes,
                ]),
            ]);

        return response()->json([
            'success' => true,
            'cities' => $cities,
        ]);
    }

    /**
     * Get single city details.
     */
    public function show(City $city): JsonResponse
    {
        $city->load(['deliveryZones' => fn ($q) => $q->active()]);

        return response()->json([
            'success' => true,
            'city' => [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'province' => $city->province,
                'cod_enabled' => $city->cod_enabled,
                'prepaid_enabled' => $city->prepaid_enabled,
                'default_delivery_fee' => (float) $city->default_delivery_fee,
                'free_delivery_minimum' => (float) $city->free_delivery_minimum,
                'estimated_delivery_minutes' => $city->estimated_delivery_minutes,
                'zones' => $city->deliveryZones,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\WaCityFloor;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class WaCityFloorController extends Controller
{
    public function index()
    {
        return response()->json(
            WaCityFloor::with('city:id,name,state')->get()
        );
    }

    public function update(Request $request, string $cityId)
    {
        $city = City::findOrFail($cityId);

        $validated = $request->validate([
            'floor_cpc' => 'required|numeric|min:0.01|max:1000',
            'max_slots' => 'required|integer|min:1|max:20',
        ]);

        $floor = WaCityFloor::updateOrCreate(
            ['city_id' => $cityId],
            [
                'floor_cpc' => $validated['floor_cpc'],
                'max_slots' => $validated['max_slots'],
                'updated_by' => $request->user()->id,
            ],
        );

        if (class_exists(AuditLogService::class)) {
            AuditLogService::log(
                action: 'wa_floor.update',
                module: 'WhatsApp Ads',
                entityType: 'wa_city_floor',
                entityId: $cityId,
                newValues: $validated,
            );
        }

        return response()->json(['message' => 'City floor updated.', 'floor' => $floor]);
    }
}

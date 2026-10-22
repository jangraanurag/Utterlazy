<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WaRecoService;
use Illuminate\Http\Request;

class InternalRecoController extends Controller
{
    public function __construct(private readonly WaRecoService $reco)
    {
    }

    /**
     * Paid WhatsApp recommendation inventory for a city.
     *
     * ONLY paid, active, budget-remaining placements are returned. Organic
     * search (NEAR_ME / name search) goes through the public catalog search
     * endpoints and is never filtered by ad spend.
     */
    public function waReco(Request $request)
    {
        $validated = $request->validate([
            'city_id' => 'required|uuid|exists:cities,id',
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        return response()->json([
            'items' => $this->reco->recommendedInventory($validated['city_id'], $validated['limit'] ?? 5),
        ]);
    }
}

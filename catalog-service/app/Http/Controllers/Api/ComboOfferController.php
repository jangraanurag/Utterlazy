<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComboOffer;
use Illuminate\Http\Request;

class ComboOfferController extends Controller
{
    public function index(Request $request)
    {
        $combos = ComboOffer::where('restaurant_id', $request->user()->id)->get();

        return response()->json(['combo_offers' => $combos]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string', 'description' => 'sometimes|string',
            'items' => 'required|array', 'combo_price' => 'required|numeric|min:0',
            'original_price' => 'required|numeric|min:0',
        ]);
        $combo = ComboOffer::create([...$validated, 'restaurant_id' => $request->user()->id]);

        return response()->json(['message' => 'Combo created', 'combo' => $combo], 201);
    }

    public function update(Request $request, ComboOffer $combo)
    {
        $combo->update($request->only(['name', 'description', 'items', 'combo_price', 'original_price', 'is_active']));

        return response()->json(['message' => 'Updated', 'combo' => $combo->fresh()]);
    }

    public function destroy(ComboOffer $combo)
    {
        $combo->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

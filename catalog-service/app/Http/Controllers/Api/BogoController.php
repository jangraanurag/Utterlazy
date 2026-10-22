<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BogoDiscount;
use Illuminate\Http\Request;

class BogoController extends Controller
{
    public function index(Request $request)
    {
        $bogos = BogoDiscount::where('restaurant_id', $request->user()->id)
            ->with('menuItems')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['bogo_discounts' => $bogos]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'buy_quantity' => 'required|integer|min:1',
            'get_quantity' => 'required|integer|min:1',
            'user_type' => 'sometimes|in:all,new_users',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i',
            'all_day' => 'sometimes|boolean',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'menu_item_ids' => 'required|array|min:1',
            'menu_item_ids.*' => 'uuid|exists:menu_items,id',
        ]);

        $validated['restaurant_id'] = $request->user()->id;
        $validated['is_active'] = true;
        $bogo = BogoDiscount::create($validated);
        $bogo->menuItems()->attach($validated['menu_item_ids']);

        return response()->json(['message' => 'BOGO created', 'bogo_discount' => $bogo->load('menuItems')], 201);
    }

    public function update(Request $request, BogoDiscount $bogoDiscount)
    {
        if ($bogoDiscount->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'buy_quantity' => 'sometimes|integer|min:1',
            'get_quantity' => 'sometimes|integer|min:1',
            'user_type' => 'sometimes|in:all,new_users',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i',
            'all_day' => 'sometimes|boolean',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'menu_item_ids' => 'sometimes|array|min:1',
            'menu_item_ids.*' => 'uuid|exists:menu_items,id',
        ]);

        $bogoDiscount->update($validated);
        if ($request->has('menu_item_ids')) {
            $bogoDiscount->menuItems()->sync($validated['menu_item_ids']);
        }

        return response()->json(['message' => 'BOGO updated', 'bogo_discount' => $bogoDiscount->fresh()->load('menuItems')]);
    }

    public function destroy(Request $request, BogoDiscount $bogoDiscount)
    {
        if ($bogoDiscount->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $bogoDiscount->delete();

        return response()->json(['message' => 'BOGO deleted']);
    }

    public function toggle(Request $request, BogoDiscount $bogoDiscount)
    {
        if ($bogoDiscount->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $bogoDiscount->update(['is_active' => ! $bogoDiscount->is_active]);

        return response()->json(['message' => 'BOGO toggled', 'is_active' => $bogoDiscount->fresh()->is_active]);
    }
}

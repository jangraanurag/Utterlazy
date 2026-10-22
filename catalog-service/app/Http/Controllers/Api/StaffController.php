<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RestaurantStaff;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = RestaurantStaff::where('restaurant_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['staff' => $staff]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'sometimes|string|max:15',
            'role' => 'required|in:owner,manager,kitchen,staff',
            'permissions' => 'sometimes|array',
        ]);

        $staff = RestaurantStaff::create([
            ...$validated,
            'restaurant_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Staff member added',
            'staff' => $staff,
        ], 201);
    }

    public function update(Request $request, RestaurantStaff $staff)
    {
        if ($staff->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|in:owner,manager,kitchen,staff',
            'permissions' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $staff->update($validated);

        return response()->json(['message' => 'Staff updated', 'staff' => $staff->fresh()]);
    }

    public function destroy(Request $request, RestaurantStaff $staff)
    {
        if ($staff->restaurant_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $staff->delete();

        return response()->json(['message' => 'Staff removed']);
    }
}

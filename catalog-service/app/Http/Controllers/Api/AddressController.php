<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    /**
     * List all addresses for the authenticated user.
     */
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['addresses' => $addresses]);
    }

    /**
     * Store a new address.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|in:Home,Work,Hotel,Other',
            'flat_building' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:500',
            'landmark' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location_name' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $validated['user_id'] = $user->id;

        // If setting as default, unset other defaults
        if (! empty($validated['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        // If this is the first address, make it default
        if ($user->addresses()->count() === 0) {
            $validated['is_default'] = true;
        }

        $address = Address::create($validated);

        return response()->json([
            'message' => 'Address added successfully.',
            'address' => $address,
        ], 201);
    }

    /**
     * Update an existing address.
     */
    public function update(Request $request, Address $address)
    {
        // Ensure user owns this address
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'label' => 'sometimes|string|in:Home,Work,Hotel,Other',
            'flat_building' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:500',
            'landmark' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location_name' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        // If setting as default, unset other defaults
        if (! empty($validated['is_default'])) {
            $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json([
            'message' => 'Address updated successfully.',
            'address' => $address->fresh(),
        ]);
    }

    /**
     * Delete an address.
     */
    public function destroy(Request $request, Address $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $wasDefault = $address->is_default;
        $address->delete();

        // If we deleted the default, assign new default
        if ($wasDefault) {
            $newDefault = $request->user()->addresses()->latest()->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return response()->json(['message' => 'Address deleted successfully.']);
    }

    /**
     * Set an address as default.
     */
    public function setDefault(Request $request, Address $address)
    {
        if ($address->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default address updated.',
            'address' => $address->fresh(),
        ]);
    }
}

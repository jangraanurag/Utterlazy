<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantApplication;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Restaurant owner submits an application to join UtterLazy
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'restaurant_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:15',
            'fssai_number' => 'sometimes|string|max:20',
            'gst_number' => 'sometimes|string|max:20',
            'pan_number' => 'sometimes|string|max:10',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'pincode' => 'required|string|max:10',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'cuisine_type' => 'sometimes|string|max:100',
            'documents' => 'sometimes|array',
            'documents.*' => 'string',
        ]);

        $application = RestaurantApplication::create($validated);

        return response()->json([
            'message' => 'Application submitted successfully! We will review it within 24-48 hours.',
            'application' => $application,
        ], 201);
    }

    /**
     * Restaurant owner checks their application status
     */
    public function status(Request $request)
    {
        $application = RestaurantApplication::where('email', $request->user()->email)
            ->latest()
            ->first();

        if (! $application) {
            return response()->json(['application' => null, 'message' => 'No application found']);
        }

        return response()->json(['application' => $application]);
    }

    /**
     * Admin: List all pending applications
     */
    public function listApplications(Request $request)
    {
        $query = RestaurantApplication::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $applications = $query->latest()->paginate(20);

        return response()->json($applications);
    }

    /**
     * Admin: Approve an application
     */
    public function approve(RestaurantApplication $application)
    {
        $application->update([
            'status' => 'approved',
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        // Create the restaurant with a default password
        $defaultPassword = 'utterlazy123';
        $restaurant = Restaurant::create([
            'name' => $application->restaurant_name,
            'email' => $application->email,
            'phone' => $application->phone,
            'description' => 'Restaurant on UtterLazy',
            'password' => bcrypt($defaultPassword),
            'latitude' => $application->latitude,
            'longitude' => $application->longitude,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Application approved. Restaurant created.',
            'application' => $application->fresh(),
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Admin: Reject an application
     */
    public function reject(Request $request, RestaurantApplication $application)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $application->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'reviewed_by' => request()->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Application rejected.',
            'application' => $application->fresh(),
        ]);
    }
}

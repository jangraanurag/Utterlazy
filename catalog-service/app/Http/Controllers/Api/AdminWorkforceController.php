<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WaWorkforce;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminWorkforceController extends Controller
{
    public function index(Request $request)
    {
        $rows = WaWorkforce::with('user:id,name,email,phone,admin_role')
            ->when($request->get('city_id'), fn ($q, $c) => $q->where('city_id', $c))
            ->orderByDesc('active')
            ->paginate($request->get('per_page', 25));

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'city_id' => 'sometimes|nullable|uuid|exists:cities,id',
            'shift_start' => 'required|date_format:H:i',
            'shift_end' => 'required|date_format:H:i|after:shift_start',
            'queue_cap' => 'required|integer|min:1|max:40',
        ]);

        $user = User::findOrFail($validated['user_id']);
        if (! $user->is_admin) {
            return response()->json(['error' => 'Only staff (admin users) can be added to workforce.'], 422);
        }

        $row = WaWorkforce::updateOrCreate(
            ['user_id' => $validated['user_id']],
            [
                'city_id' => $validated['city_id'] ?? null,
                'shift_start' => $validated['shift_start'],
                'shift_end' => $validated['shift_end'],
                'queue_cap' => $validated['queue_cap'],
                'active' => true,
            ],
        );

        return response()->json(['message' => 'Workforce entry saved.', 'entry' => $row->load('user:id,name,admin_role')], 201);
    }

    public function update(Request $request, WaWorkforce $entry)
    {
        $validated = $request->validate([
            'city_id' => 'sometimes|nullable|uuid|exists:cities,id',
            'shift_start' => 'sometimes|date_format:H:i',
            'shift_end' => 'sometimes|date_format:H:i',
            'queue_cap' => 'sometimes|integer|min:1|max:40',
            'active' => 'sometimes|boolean',
        ]);

        if (isset($validated['shift_start'], $validated['shift_end']) && $validated['shift_end'] <= $validated['shift_start']) {
            return response()->json(['error' => 'Shift end must be after shift start.'], 422);
        }

        $entry->update($validated);

        return response()->json(['message' => 'Workforce entry updated.', 'entry' => $entry->fresh('user:id,name,admin_role')]);
    }
}

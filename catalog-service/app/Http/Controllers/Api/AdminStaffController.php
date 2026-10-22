<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminStaffController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('is_admin', true)
            ->when($request->get('role'), fn ($q, $r) => $q->where('admin_role', $r))
            ->when($request->get('q'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            }));

        return response()->json($query->latest()->paginate($request->get('per_page', 25)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'required|regex:/^\+91[6-9][0-9]{9}$/|unique:users,phone',
            'admin_role' => ['required', Rule::in(array_keys(AdminAuth::roles()))],
        ]);

        $staff = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make(Str::random(24)),
            'is_admin' => true,
            'admin_role' => $validated['admin_role'],
        ]);

        return response()->json([
            'message' => 'Staff member created. Password reset link must be sent via email. Assign org unit via Org Chart.',
            'staff' => $staff->only(['id', 'name', 'email', 'phone', 'admin_role']),
        ], 201);
    }

    public function deactivate(Request $request, User $user)
    {
        if (! $user->is_admin) {
            return response()->json(['error' => 'Not an admin user.'], 422);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['error' => 'You cannot deactivate yourself.'], 422);
        }

        $user->update(['is_admin' => false]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Staff member deactivated. Audit history is preserved.']);
    }
}

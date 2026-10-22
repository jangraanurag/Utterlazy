<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        // Mocking user 1 for the demonstration
        $user = User::find(1);

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $orders = Order::where('user_id', 1)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'user' => $user,
            'recent_orders' => $orders,
        ]);
    }
}

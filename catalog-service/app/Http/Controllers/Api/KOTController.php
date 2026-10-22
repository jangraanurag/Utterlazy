<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KOTPrintService;
use Illuminate\Http\Request;

class KOTController extends Controller
{
    public function print(Request $request, Order $order)
    {
        $restaurant = $request->user();
        if ($order->restaurant_id !== $restaurant->id) {
            return response()->json(['error' => 'Not your order.'], 403);
        }

        $service = new KOTPrintService;
        $kot = $service->generateKOT($order);

        return response()->json($kot);
    }
}

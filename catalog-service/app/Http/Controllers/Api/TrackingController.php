<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\TrackingService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    protected TrackingService $trackingService;

    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    public function track(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your order.'], 403);
        }

        return response()->json(
            $this->trackingService->getOrderTracking($order)
        );
    }
}

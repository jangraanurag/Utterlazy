<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Projection\OrderProjectionService;

class ProjectionController extends Controller
{
    public function status(OrderProjectionService $projection)
    {
        return response()->json([
            'projection' => $projection->status(),
            'source' => 'order-command',
            'consumer' => OrderProjectionService::CONSUMER,
        ]);
    }
}

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GatewayProxyController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Middleware\ForwardVendorContext;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// FCM token management (authenticated)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/fcm/token', [FcmTokenController::class, 'store']);
    Route::delete('/fcm/token', [FcmTokenController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', ForwardVendorContext::class])->group(function () {
    Route::any('/catalog/{path}', [GatewayProxyController::class, 'forwardToCatalog'])
        ->where('path', '.*')
        ->name('catalog.proxy.private');
});

Route::get('/public/catalog/{path}', [GatewayProxyController::class, 'forwardToCatalog'])
    ->where('path', '.*')
    ->name('catalog.proxy.public');

// Accept orders (mock forward to order service, or handle here directly for prototype)
Route::post('/orders', function (Request $request) {
    $orderId = 'ORD-' . rand(1000, 9999);
    $vendorId = $request->input('vendor_id', 1);

    // Dispatch event that triggers FCM notification to vendor
    \App\Events\NewOrderPlaced::dispatch(
        [
            'id' => $orderId,
            'grand_total' => $request->input('grand_total', 299),
            'restaurant_name' => $request->input('restaurant_name', 'Test Restaurant'),
            'delivery_address_text' => $request->input('delivery_address', '123 Main St'),
        ],
        (int) $vendorId
    );

    return response()->json([
        'message' => 'Order placed successfully',
        'order_id' => $orderId,
    ], 201);
});

// Catch-all: forward unrecognized API paths to catalog service
// These handle mobile app requests that don't use the /catalog prefix
Route::any('/{path}', [GatewayProxyController::class, 'forwardToCatalog'])
    ->where('path', '.*')
    ->name('catalog.proxy.all');

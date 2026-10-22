<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Register all the broadcast channels for the application.
|
*/

// Order tracking channel — customer can listen to their order updates
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    // In production: verify the user owns this order
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

// Rider location channel — customer can track their rider
Broadcast::channel('rider.{riderId}', function ($user, $riderId) {
    // In production: verify the user has an active order with this rider
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

// Vendor channel — vendor receives new order notifications
Broadcast::channel('vendor.{vendorId}', function ($user, $vendorId) {
    // In production: verify the user is the vendor/restaurant owner
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

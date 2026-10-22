<?php

use App\Http\Controllers\InternalController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\ServiceAuth;
use Illuminate\Support\Facades\Route;

Route::get('/webhook/meta', [WebhookController::class, 'verify']);
Route::post('/webhook/meta', [WebhookController::class, 'receive']);

Route::prefix('internal')->middleware(ServiceAuth::class)->group(function () {
    Route::get('/sessions', [InternalController::class, 'sessions']);
    Route::post('/sessions/{waId}/assign', [InternalController::class, 'assign']);
    Route::post('/reco/push', [InternalController::class, 'pushReco']);
    Route::get('/carts/{waId}', [InternalController::class, 'cart']);
});

Route::get('/health', fn () => response()->json(['status' => 'healthy']));

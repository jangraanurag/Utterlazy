<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GatewayProxyController;

Route::get('/', function () {
    return view('welcome');
});

// Static assets (images, uploads) → catalog service public dir (no /api prefix)
// Public URL: https://api.utterlazy.com/images/xxx.png
Route::get('/images/{path}', [GatewayProxyController::class, 'forwardAsset'])
    ->where('path', '.*')
    ->name('catalog.asset.images');

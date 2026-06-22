<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MarketplaceWebhookController;

// Telegram Webhook - support multi-tenant
Route::post('/telegram/webhook/{tenant?}', [TelegramWebhookController::class, 'handle']);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/sales-orders', [SalesOrderController::class, 'index']);
    Route::post('/sales-orders', [SalesOrderController::class, 'store']);
});

// Webhook untuk TikTok & Shopee — tanpa CSRF
Route::post('/webhook/tiktok/{tenant}', [MarketplaceWebhookController::class, 'tiktok'])
    ->name('webhook.tiktok');

Route::post('/webhook/shopee/{tenant}', [MarketplaceWebhookController::class, 'shopee'])
    ->name('webhook.shopee');
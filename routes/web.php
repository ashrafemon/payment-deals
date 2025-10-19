<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->group(function () {
    Route::apiResource('payment-gateways', \Leafwrap\PaymentDeals\Http\Controllers\PaymentGatewayController::class)->except(['create', 'edit']);
    Route::get('online-payment-check/{transactionId}', \Leafwrap\PaymentDeals\Http\Controllers\PaymentController::class);
});

Route::view('online-payment-status', 'payment-deal::pages.payment');

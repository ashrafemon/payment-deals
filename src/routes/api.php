<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api/v1')->group(function () {
    Route::apiResource('payment-gateways', \Leafwrap\PaymentDeals\Http\Controllers\PaymentGatewayController::class)->except(['create', 'edit']);
});

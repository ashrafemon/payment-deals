<?php

use Illuminate\Support\Facades\Route;
use Leafwrap\PaymentDeals\Http\Controllers\PaymentGatewayController;

Route::middleware('api')->prefix('api/v1')->group(function () {
    Route::apiResource('payment-gateways', PaymentGatewayController::class)->except(['create', 'edit']);
});

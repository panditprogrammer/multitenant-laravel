<?php

use App\Http\Controllers\RazorpayWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/payments/webhooks/razorpay', RazorpayWebhookController::class)
    ->name('payments.webhooks.razorpay');

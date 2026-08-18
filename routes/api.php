<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderCompletedController;

Route::post('/webhooks/order-completed', [OrderCompletedController::class, '__invoke'])
  ->middleware('verify.webhook');

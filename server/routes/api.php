<?php

use App\Http\Controllers\Api\ChannelReservationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\PaidSessionController;
use App\Http\Controllers\Api\PaymentFlowController;
use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::get('/devices/{deviceId}', [DeviceController::class, 'show']);
Route::post('/devices/{deviceId}/channels/{channel}/reserve', [ChannelReservationController::class, 'store']);
Route::post('/devices/{deviceId}/telemetry', [TelemetryController::class, 'store']);

Route::post('/payments', [PaymentFlowController::class, 'create'])->name('api.payments.create');
Route::get('/payments/{paymentId}', [PaymentFlowController::class, 'status'])->name('api.payments.status');
Route::post('/test-payments/{paymentId}/pay', [PaymentFlowController::class, 'testPay'])->name('api.test-payments.pay');
Route::get('/sessions/{sessionId}/command', [PaidSessionController::class, 'show'])->name('api.sessions.command');

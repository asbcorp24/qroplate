<?php

use App\Http\Controllers\Api\ChannelReservationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::get('/devices/{deviceId}', [DeviceController::class, 'show']);
Route::post('/devices/{deviceId}/channels/{channel}/reserve', [ChannelReservationController::class, 'store']);
Route::post('/devices/{deviceId}/telemetry', [TelemetryController::class, 'store']);

<?php

use App\Http\Controllers\Admin\DeviceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin/devices');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::put('/devices/{device}/channels/{channel}', [DeviceController::class, 'updateChannel'])->name('devices.channels.update');
    Route::post('/devices/{device}/channels/{channel}/tariffs', [DeviceController::class, 'storeTariff'])->name('devices.channels.tariffs.store');
});

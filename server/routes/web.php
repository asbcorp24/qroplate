<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\AdminSessionAuth;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::redirect('/', '/admin/dashboard');

Route::middleware(AdminSessionAuth::class)->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/create', [DeviceController::class, 'create'])->name('devices.create');
    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('/devices/{device}/edit', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::put('/devices/{device}/channels/{channel}', [DeviceController::class, 'updateChannel'])->name('devices.channels.update');
    Route::post('/devices/{device}/channels/{channel}/tariffs', [DeviceController::class, 'storeTariff'])->name('devices.channels.tariffs.store');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments.csv', [PaymentController::class, 'csv'])->name('payments.csv');

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions.csv', [SessionController::class, 'csv'])->name('sessions.csv');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('/admins', [UserAdminController::class, 'index'])->name('admins.index');
    Route::post('/admins', [UserAdminController::class, 'store'])->name('admins.store');
    Route::put('/admins/{admin}', [UserAdminController::class, 'update'])->name('admins.update');
});

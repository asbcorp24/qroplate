<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Device;
use App\Models\DeviceChannel;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $isSuper = (bool) session('admin_is_super', false);
        $adminId = session('admin_id');

        $devices = Device::query()
            ->when(!$isSuper, fn($q) => $q->where('owner_admin_id', $adminId))
            ->get();

        $deviceIds = $devices->pluck('id');
        $channels = DeviceChannel::query()->whereIn('device_id_fk', $deviceIds)->get();

        $statusCounts = ['free'=>0,'reserved'=>0,'running'=>0,'error'=>0,'disabled'=>0];
        foreach ($channels as $channel) {
            $status = $channel->effectiveStatus();
            if (isset($statusCounts[$status])) $statusCounts[$status]++;
        }

        return view('admin.dashboard', [
            'isSuper' => $isSuper,
            'adminCount' => $isSuper ? Admin::count() : null,
            'deviceCount' => $devices->count(),
            'channelCount' => $channels->count(),
            'statusCounts' => $statusCounts,
        ]);
    }
}

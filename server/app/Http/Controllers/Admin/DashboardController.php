<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Device;
use App\Models\DeviceChannel;
use App\Services\DashboardStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $isSuper = session('admin_role') === 'super';
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

        $stats = DashboardStats::forChannels($channels->pluck('id'));

        return view('admin.dashboard', array_merge($stats, [
            'isSuper' => $isSuper,
            'adminCount' => $isSuper ? Admin::count() : null,
            'deviceCount' => $devices->count(),
            'channelCount' => $channels->count(),
            'statusCounts' => $statusCounts,
        ]));
    }
}

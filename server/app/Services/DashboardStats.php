<?php

namespace App\Services;

use App\Models\DeviceSession;
use App\Models\Payment;

class DashboardStats
{
    public static function forChannels($channelIds): array
    {
        $payments = Payment::query()->whereIn('device_channel_id', $channelIds);
        $sessions = DeviceSession::query()->whereIn('device_channel_id', $channelIds);

        return [
            'paidCount' => (clone $payments)->where('status', 'paid')->count(),
            'revenueTotal' => (clone $payments)->where('status', 'paid')->sum('amount'),
            'revenueToday' => (clone $payments)->where('status', 'paid')->whereDate('paid_at', today())->sum('amount'),
            'activeSessions' => (clone $sessions)->whereIn('status', ['active', 'running'])->count(),
            'recentPayments' => (clone $payments)->with(['channel.device', 'tariff'])->latest('id')->limit(10)->get(),
            'recentSessions' => (clone $sessions)->with(['channel.device', 'payment'])->latest('id')->limit(10)->get(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $isSuper = $request->session()->get('admin_role') === 'super';
        $adminId = (int) $request->session()->get('admin_id');

        $deviceQuery = Device::query()->when(!$isSuper, fn($q) => $q->where('owner_admin_id', $adminId));
        $devices = $deviceQuery->orderBy('name')->get();
        $deviceIds = $devices->pluck('id');

        $payments = Payment::query()
            ->with(['channel.device'])
            ->whereHas('channel', fn(Builder $q) => $q->whereIn('device_id_fk', $deviceIds));

        $sessions = DeviceSession::query()
            ->with(['channel.device'])
            ->whereHas('channel', fn(Builder $q) => $q->whereIn('device_id_fk', $deviceIds));

        if ($request->filled('date_from')) {
            $payments->whereDate('created_at', '>=', $request->date_from);
            $sessions->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $payments->whereDate('created_at', '<=', $request->date_to);
            $sessions->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('device_id')) {
            $deviceId = (int) $request->device_id;
            $payments->whereHas('channel', fn(Builder $q) => $q->where('device_id_fk', $deviceId));
            $sessions->whereHas('channel', fn(Builder $q) => $q->where('device_id_fk', $deviceId));
        }
        if ($request->filled('channel')) {
            $channel = (int) $request->channel;
            $payments->whereHas('channel', fn(Builder $q) => $q->where('channel', $channel));
            $sessions->whereHas('channel', fn(Builder $q) => $q->where('channel', $channel));
        }
        if ($isSuper && $request->filled('admin_id')) {
            $filterAdminId = (int) $request->admin_id;
            $payments->whereHas('channel.device', fn(Builder $q) => $q->where('owner_admin_id', $filterAdminId));
            $sessions->whereHas('channel.device', fn(Builder $q) => $q->where('owner_admin_id', $filterAdminId));
        }

        $paidPayments = (clone $payments)->where('status', 'paid')->get();
        $allSessions = $sessions->get();

        $summary = [
            'revenue' => (float) $paidPayments->sum('amount'),
            'payments' => $paidPayments->count(),
            'sessions' => $allSessions->count(),
            'running' => $allSessions->where('status', 'running')->count(),
            'finished' => $allSessions->where('status', 'finished')->count(),
            'duration_sec' => (int) $allSessions->sum('duration_sec'),
        ];

        $rows = $devices->map(function ($device) use ($paidPayments, $allSessions) {
            $devicePayments = $paidPayments->filter(fn($p) => optional($p->channel)->device_id_fk === $device->id);
            $deviceSessions = $allSessions->filter(fn($s) => optional($s->channel)->device_id_fk === $device->id);
            return [
                'device' => $device,
                'revenue' => (float) $devicePayments->sum('amount'),
                'payments' => $devicePayments->count(),
                'sessions' => $deviceSessions->count(),
                'duration_sec' => (int) $deviceSessions->sum('duration_sec'),
            ];
        })->filter(fn($row) => $row['payments'] || $row['sessions']);

        $admins = $isSuper ? Admin::orderBy('name')->get() : collect();
        return view('admin.reports.index', compact('summary','rows','devices','admins','isSuper'));
    }
}

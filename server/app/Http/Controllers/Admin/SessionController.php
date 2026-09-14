<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Device;
use App\Models\DeviceSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SessionController extends Controller
{
    private function baseQuery(Request $request): Builder
    {
        $query = DeviceSession::query()->with(['channel.device','payment']);
        if ($request->session()->get('admin_role') !== 'super') {
            $adminId = (int) $request->session()->get('admin_id');
            $query->whereHas('channel.device', fn(Builder $q) => $q->where('owner_admin_id', $adminId));
        }
        return $query;
    }

    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to')) $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('device_id')) {
            $deviceId = (int) $request->device_id;
            $query->whereHas('channel', fn(Builder $q) => $q->where('device_id_fk', $deviceId));
        }
        if ($request->filled('channel')) {
            $channel = (int) $request->channel;
            $query->whereHas('channel', fn(Builder $q) => $q->where('channel', $channel));
        }
        if ($request->session()->get('admin_role') === 'super' && $request->filled('admin_id')) {
            $adminId = (int) $request->admin_id;
            $query->whereHas('channel.device', fn(Builder $q) => $q->where('owner_admin_id', $adminId));
        }
        return $query;
    }

    public function index(Request $request)
    {
        $query = $this->applyFilters($this->baseQuery($request), $request);
        $sessions = $query->latest()->paginate(50)->withQueryString();
        $devices = Device::query()
            ->when($request->session()->get('admin_role') !== 'super', fn($q) => $q->where('owner_admin_id', $request->session()->get('admin_id')))
            ->orderBy('name')->get();
        $admins = $request->session()->get('admin_role') === 'super' ? Admin::orderBy('name')->get() : collect();
        return view('admin.sessions.index', compact('sessions','devices','admins'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $sessions = $this->applyFilters($this->baseQuery($request), $request)->latest()->get();
        return response()->streamDownload(function () use ($sessions) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Дата','Session ID','Прибор','Канал','Payment ID','Длительность, сек','Статус','Начало','Окончание','Завершено'], ';');
            foreach ($sessions as $s) {
                fputcsv($out, [
                    optional($s->created_at)->format('d.m.Y H:i:s'), $s->session_id,
                    optional(optional($s->channel)->device)->name,
                    optional($s->channel)->channel,
                    optional($s->payment)->payment_id,
                    $s->duration_sec, $s->status,
                    optional($s->started_at)->format('d.m.Y H:i:s'),
                    optional($s->ends_at)->format('d.m.Y H:i:s'),
                    optional($s->finished_at)->format('d.m.Y H:i:s')
                ], ';');
            }
            fclose($out);
        }, 'sessions_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

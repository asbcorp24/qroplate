<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function buildData(Request $request): array
    {
        $isSuper = $request->session()->get('admin_role') === 'super';
        $adminId = (int) $request->session()->get('admin_id');

        $deviceQuery = Device::query()->when(!$isSuper, fn($q) => $q->where('owner_admin_id', $adminId));
        if ($isSuper && $request->filled('admin_id')) {
            $deviceQuery->where('owner_admin_id', (int) $request->admin_id);
        }
        if ($request->filled('device_id')) {
            $deviceQuery->whereKey((int) $request->device_id);
        }
        $devices = $deviceQuery->orderBy('name')->get();
        $deviceIds = $devices->pluck('id');

        $payments = Payment::query()->with(['channel.device'])
            ->whereHas('channel', fn(Builder $q) => $q->whereIn('device_id_fk', $deviceIds));
        $sessions = DeviceSession::query()->with(['channel.device'])
            ->whereHas('channel', fn(Builder $q) => $q->whereIn('device_id_fk', $deviceIds));

        if ($request->filled('date_from')) {
            $payments->whereDate('created_at', '>=', $request->date_from);
            $sessions->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $payments->whereDate('created_at', '<=', $request->date_to);
            $sessions->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('channel')) {
            $channel = (int) $request->channel;
            $payments->whereHas('channel', fn(Builder $q) => $q->where('channel', $channel));
            $sessions->whereHas('channel', fn(Builder $q) => $q->where('channel', $channel));
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

        return compact('isSuper','devices','summary','rows');
    }

    public function index(Request $request)
    {
        $data = $this->buildData($request);
        $data['admins'] = $data['isSuper'] ? Admin::orderBy('name')->get() : collect();
        return view('admin.reports.index', $data);
    }

    public function csv(Request $request): StreamedResponse
    {
        $data = $this->buildData($request);
        $summary = $data['summary'];
        $rows = $data['rows'];

        return response()->streamDownload(function () use ($summary, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Показатель','Значение'], ';');
            fputcsv($out, ['Оборот', $summary['revenue']], ';');
            fputcsv($out, ['Оплаченных платежей', $summary['payments']], ';');
            fputcsv($out, ['Сессий', $summary['sessions']], ';');
            fputcsv($out, ['Активных сессий', $summary['running']], ';');
            fputcsv($out, ['Завершённых сессий', $summary['finished']], ';');
            fputcsv($out, ['Оплачено секунд', $summary['duration_sec']], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['Device ID','Прибор','Оборот','Платежи','Сессии','Оплачено секунд'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['device']->device_id,
                    $row['device']->name,
                    $row['revenue'],
                    $row['payments'],
                    $row['sessions'],
                    $row['duration_sec'],
                ], ';');
            }
            fclose($out);
        }, 'report_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

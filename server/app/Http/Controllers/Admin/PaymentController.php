<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Device;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    private function baseQuery(Request $request): Builder
    {
        $query = Payment::query()->with(['channel.device','tariff']);
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
        $payments = $query->latest()->paginate(50)->withQueryString();
        $devices = Device::query()
            ->when($request->session()->get('admin_role') !== 'super', fn($q) => $q->where('owner_admin_id', $request->session()->get('admin_id')))
            ->orderBy('name')->get();
        $admins = $request->session()->get('admin_role') === 'super' ? Admin::orderBy('name')->get() : collect();
        return view('admin.payments.index', compact('payments','devices','admins'));
    }

    public function csv(Request $request): StreamedResponse
    {
        $payments = $this->applyFilters($this->baseQuery($request), $request)->latest()->get();
        return response()->streamDownload(function () use ($payments) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Дата','Payment ID','Прибор','Канал','Тариф','Сумма','Валюта','Статус','Провайдер'], ';');
            foreach ($payments as $p) {
                fputcsv($out, [
                    optional($p->created_at)->format('d.m.Y H:i:s'), $p->payment_id,
                    optional(optional($p->channel)->device)->name,
                    optional($p->channel)->channel,
                    optional($p->tariff)->title,
                    $p->amount, $p->currency, $p->status, $p->provider
                ], ';');
            }
            fclose($out);
        }, 'payments_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceChannel;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        $devices = Device::with('channels')->orderBy('name')->get();
        return view('admin.devices.index', compact('devices'));
    }

    public function edit(Device $device): View
    {
        $device->load(['channels' => fn($q) => $q->orderBy('channel')->with(['tariffs' => fn($t) => $t->orderBy('sort_order')])]);
        return view('admin.devices.edit', compact('device'));
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate([
            'name'=>'required|string|max:120','type'=>'required|string|max:80','location'=>'nullable|string|max:255',
            'title'=>'nullable|string|max:160','subtitle'=>'nullable|string|max:160','description'=>'nullable|string',
            'image_url'=>'nullable|string|max:500','accent_color'=>'nullable|string|max:20','icon'=>'nullable|string|max:80',
            'layout'=>'nullable|string|max:80','maintenance_message'=>'nullable|string','enabled'=>'nullable|boolean'
        ]);
        $data['enabled'] = $request->boolean('enabled');
        $device->update($data);
        return back()->with('success', 'Устройство сохранено');
    }

    public function updateChannel(Request $request, Device $device, DeviceChannel $channel): RedirectResponse
    {
        abort_unless($channel->device_id_fk === $device->id, 404);
        $data = $request->validate(['name'=>'required|string|max:120','enabled'=>'nullable|boolean']);
        $channel->update(['name'=>$data['name'],'enabled'=>$request->boolean('enabled')]);
        return back()->with('success', 'Канал сохранён');
    }

    public function storeTariff(Request $request, Device $device, DeviceChannel $channel): RedirectResponse
    {
        abort_unless($channel->device_id_fk === $device->id, 404);
        $data = $request->validate([
            'code'=>'required|string|max:100|unique:tariffs,code','title'=>'required|string|max:120',
            'duration_sec'=>'required|integer|min:1|max:43200','price'=>'required|numeric|min:0',
            'currency'=>'required|string|size:3'
        ]);
        $data['device_channel_id'] = $channel->id;
        Tariff::create($data);
        return back()->with('success', 'Тариф добавлен');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    public function show(string $deviceId): JsonResponse
    {
        $device = Device::where('device_id', $deviceId)
            ->with(['channels' => function ($q) {
                $q->orderBy('channel')->with(['tariffs' => function ($t) {
                    $t->where('enabled', true)->orderBy('sort_order')->orderBy('price');
                }]);
            }])->firstOrFail();

        return response()->json([
            'device_id' => $device->device_id,
            'enabled' => $device->enabled,
            'type' => $device->type,
            'title' => $device->title ?: $device->name,
            'subtitle' => $device->subtitle,
            'description' => $device->description,
            'location' => $device->location,
            'image_url' => $device->image_url,
            'theme' => [
                'accent' => $device->accent_color,
                'icon' => $device->icon,
                'layout' => $device->layout,
            ],
            'maintenance_message' => $device->maintenance_message,
            'channels' => $device->channels->map(fn($channel) => [
                'channel' => $channel->channel,
                'name' => $channel->name,
                'enabled' => $channel->enabled,
                'status' => $channel->effectiveStatus(),
                'reserved_until' => optional($channel->reserved_until)->toIso8601String(),
                'occupied_until' => optional($channel->occupied_until)->toIso8601String(),
                'tariffs' => $channel->tariffs->map(fn($tariff) => [
                    'id' => $tariff->code,
                    'title' => $tariff->title,
                    'duration_sec' => $tariff->duration_sec,
                    'price' => (float)$tariff->price,
                    'currency' => $tariff->currency,
                ])->values(),
            ])->values(),
        ]);
    }
}

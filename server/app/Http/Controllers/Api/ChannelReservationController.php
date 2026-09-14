<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceChannel;
use App\Models\Tariff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelReservationController extends Controller
{
    public function store(Request $request, string $deviceId, int $channel): JsonResponse
    {
        $data = $request->validate([
            'tariff_id' => ['required','string','max:100'],
        ]);

        $result = DB::transaction(function () use ($deviceId, $channel, $data) {
            $device = Device::where('device_id', $deviceId)->firstOrFail();

            $row = DeviceChannel::where('device_id_fk', $device->id)
                ->where('channel', $channel)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$device->enabled || !$row->enabled) {
                return ['error' => 'channel_disabled', 'status' => 409];
            }

            if ($row->effectiveStatus() !== 'free') {
                return ['error' => 'channel_busy', 'status' => 409];
            }

            $tariff = Tariff::where('device_channel_id', $row->id)
                ->where('code', $data['tariff_id'])
                ->where('enabled', true)
                ->firstOrFail();

            $reservationId = 'RES-'.Str::upper(Str::random(20));
            $reservedUntil = now()->addMinutes((int) config('qroplate.reservation_minutes', 2));

            $row->update([
                'status' => 'reserved',
                'current_session_id' => $reservationId,
                'reserved_until' => $reservedUntil,
                'occupied_until' => null,
            ]);

            return [
                'reservation_id' => $reservationId,
                'device_id' => $device->device_id,
                'channel' => $row->channel,
                'tariff' => [
                    'id' => $tariff->code,
                    'title' => $tariff->title,
                    'duration_sec' => $tariff->duration_sec,
                    'price' => (float)$tariff->price,
                    'currency' => $tariff->currency,
                ],
                'reserved_until' => $reservedUntil->toIso8601String(),
                'status' => 201,
            ];
        });

        $status = $result['status'];
        unset($result['status']);
        return response()->json($result, $status);
    }
}

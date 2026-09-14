<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceChannel;
use App\Models\DeviceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelemetryController extends Controller
{
    public function store(Request $request, string $deviceId): JsonResponse
    {
        $data = $request->validate([
            'channels' => ['required','array','min:1','max:4'],
            'channels.*.channel' => ['required','integer','between:1,4'],
            'channels.*.running' => ['required','boolean'],
            'channels.*.remaining_sec' => ['nullable','integer','min:0'],
            'channels.*.end_epoch' => ['nullable','integer','min:0'],
            'channels.*.session_id' => ['nullable','string','max:120'],
            'channels.*.payment_id' => ['nullable','string','max:120'],
        ]);

        $device = Device::where('device_id', $deviceId)->firstOrFail();

        DB::transaction(function () use ($device, $data) {
            foreach ($data['channels'] as $reported) {
                $channel = DeviceChannel::where('device_id_fk', $device->id)
                    ->where('channel', $reported['channel'])
                    ->lockForUpdate()
                    ->first();
                if (!$channel) continue;

                $running = (bool)$reported['running'];
                $endEpoch = (int)($reported['end_epoch'] ?? 0);
                $occupiedUntil = $running && $endEpoch > 0
                    ? \Carbon\Carbon::createFromTimestampUTC($endEpoch)
                    : null;

                $channel->update([
                    'status' => $running ? 'running' : 'free',
                    'current_session_id' => $running ? ($reported['session_id'] ?? $channel->current_session_id) : null,
                    'current_payment_id' => $running ? ($reported['payment_id'] ?? $channel->current_payment_id) : null,
                    'reserved_until' => null,
                    'occupied_until' => $occupiedUntil,
                    'last_device_report_at' => now(),
                ]);

                if (!empty($reported['session_id'])) {
                    $session = DeviceSession::where('session_id', $reported['session_id'])->first();
                    if ($session) {
                        $session->update([
                            'status' => $running ? 'running' : 'finished',
                            'started_at' => $running ? ($session->started_at ?: now()) : $session->started_at,
                            'ends_at' => $occupiedUntil ?: $session->ends_at,
                            'finished_at' => $running ? null : now(),
                            'last_device_status' => $reported,
                        ]);
                    }
                }
            }
        });

        return response()->json(['ok' => true]);
    }
}

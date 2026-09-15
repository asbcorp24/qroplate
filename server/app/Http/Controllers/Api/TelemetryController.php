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
                $remainingSec = (int)($reported['remaining_sec'] ?? 0);

                $occupiedUntil = null;
                if ($running) {
                    if ($endEpoch > 0) {
                        $occupiedUntil = \Carbon\Carbon::createFromTimestampUTC($endEpoch);
                    } elseif ($remainingSec > 0) {
                        // No-RTC ESP32 bench mode reports remaining seconds but no Unix end epoch.
                        $occupiedUntil = now()->addSeconds($remainingSec);
                    }
                }

                $previousSessionId = $channel->current_session_id;
                $reportedSessionId = (string)($reported['session_id'] ?? '');
                $reportedPaymentId = (string)($reported['payment_id'] ?? '');

                if ($running) {
                    $channel->update([
                        'status' => 'running',
                        'current_session_id' => $reportedSessionId !== '' ? $reportedSessionId : $channel->current_session_id,
                        'current_payment_id' => $reportedPaymentId !== '' ? $reportedPaymentId : $channel->current_payment_id,
                        'reserved_until' => null,
                        'occupied_until' => $occupiedUntil,
                        'last_device_report_at' => now(),
                    ]);

                    $sessionId = $reportedSessionId !== '' ? $reportedSessionId : $previousSessionId;
                    if ($sessionId) {
                        $session = DeviceSession::where('session_id', $sessionId)->first();
                        if ($session) {
                            $session->update([
                                'status' => 'running',
                                'started_at' => $session->started_at ?: now(),
                                'ends_at' => $occupiedUntil ?: $session->ends_at,
                                'finished_at' => null,
                                'last_device_status' => $reported,
                            ]);
                        }
                    }
                    continue;
                }

                // ESP reports only physical relay state. A valid server-side reservation
                // must not be erased simply because its relay has not started yet.
                if ($channel->status === 'reserved' && $channel->reserved_until && $channel->reserved_until->isFuture()) {
                    $channel->update(['last_device_report_at' => now()]);
                    continue;
                }

                $channel->update([
                    'status' => 'free',
                    'current_session_id' => null,
                    'current_payment_id' => null,
                    'reserved_until' => null,
                    'occupied_until' => null,
                    'last_device_report_at' => now(),
                ]);

                $sessionId = $reportedSessionId !== '' ? $reportedSessionId : $previousSessionId;
                if ($sessionId && !str_starts_with($sessionId, 'RES-')) {
                    $session = DeviceSession::where('session_id', $sessionId)->first();
                    if ($session && $session->status === 'running') {
                        $session->update([
                            'status' => 'finished',
                            'finished_at' => now(),
                            'last_device_status' => $reported,
                        ]);
                    }
                }
            }
        });

        return response()->json(['ok' => true]);
    }
}

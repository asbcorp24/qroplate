<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceSession;
use Illuminate\Http\JsonResponse;

class PaidSessionController extends Controller
{
    public function show(string $sessionId): JsonResponse
    {
        $session = DeviceSession::with(['channel.device','payment.tariff'])
            ->where('session_id', $sessionId)->firstOrFail();

        if (!$session->payment || $session->payment->status !== 'paid') {
            return response()->json(['error'=>'payment_not_paid'], 409);
        }

        $issuedAt = now()->timestamp;
        $expiresAt = $issuedAt + (int) config('qroplate.session_command_ttl_seconds', 120);

        return response()->json([
            'version'=>1,
            'device_id'=>$session->channel->device->device_id,
            'payment_id'=>$session->payment->payment_id,
            'session_id'=>$session->session_id,
            'tariff_id'=>$session->payment->tariff->code,
            'relay_channel'=>(int)$session->channel->channel,
            'duration_sec'=>(int)$session->duration_sec,
            'issued_at'=>$issuedAt,
            'expires_at'=>$expiresAt,
            'nonce'=>$session->nonce,
            'token'=>null,
            'token_status'=>'signer_not_configured',
        ], 503);
    }
}

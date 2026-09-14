<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceSession;
use App\Services\HmacSessionCommandSigner;
use Illuminate\Http\JsonResponse;

class PaidSessionController extends Controller
{
    public function show(string $sessionId, HmacSessionCommandSigner $signer): JsonResponse
    {
        $session = DeviceSession::with(['channel.device','payment.tariff'])
            ->where('session_id', $sessionId)->firstOrFail();

        if (!$session->payment || $session->payment->status !== 'paid') {
            return response()->json(['error'=>'payment_not_paid'], 409);
        }

        if ((string) config('qroplate.device_token_secret') === '') {
            return response()->json(['error'=>'device_token_secret_not_configured'], 503);
        }

        $issuedAt = now()->timestamp;
        $expiresAt = $issuedAt + (int) config('qroplate.session_command_ttl_seconds', 120);

        $payload = [
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
        ];

        $payload['token'] = $signer->sign($payload);
        return response()->json($payload);
    }
}

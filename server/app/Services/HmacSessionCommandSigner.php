<?php

namespace App\Services;

use App\Contracts\SessionCommandSigner;

class HmacSessionCommandSigner implements SessionCommandSigner
{
    public function sign(array $payload): string
    {
        $canonical = implode('|', [
            'QRP1',
            $payload['device_id'],
            $payload['payment_id'],
            $payload['session_id'],
            $payload['tariff_id'],
            $payload['relay_channel'],
            $payload['duration_sec'],
            $payload['issued_at'],
            $payload['expires_at'],
            $payload['nonce'],
        ]);

        return hash_hmac('sha256', $canonical, (string) config('qroplate.device_token_secret'));
    }
}

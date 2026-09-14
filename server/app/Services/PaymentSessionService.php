<?php

namespace App\Services;

use App\Models\DeviceSession;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentSessionService
{
    public function markPaidAndCreateSession(Payment $payment): DeviceSession
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $payment->load(['channel.device','tariff','session']);

            if ($payment->status !== 'paid') {
                $payment->update(['status' => 'paid', 'paid_at' => now()]);
            }

            if ($payment->session) return $payment->session;

            $session = DeviceSession::create([
                'session_id' => 'SES-'.Str::upper(Str::random(24)),
                'device_channel_id' => $payment->device_channel_id,
                'payment_id_fk' => $payment->id,
                'duration_sec' => $payment->tariff->duration_sec,
                'nonce' => Str::lower(Str::random(32)),
                'status' => 'paid',
            ]);

            $payment->channel->update([
                'status' => 'reserved',
                'current_payment_id' => $payment->payment_id,
                'current_session_id' => $session->session_id,
            ]);

            return $session;
        });
    }
}

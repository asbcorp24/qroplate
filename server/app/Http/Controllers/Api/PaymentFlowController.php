<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceChannel;
use App\Models\Payment;
use App\Models\Tariff;
use App\Services\PaymentSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentFlowController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reservation_id' => ['required','string','max:100'],
            'tariff_id' => ['required','string','max:100'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $channel = DeviceChannel::where('current_session_id', $data['reservation_id'])
                ->lockForUpdate()->first();

            if (!$channel || $channel->effectiveStatus() !== 'reserved') {
                return ['error'=>'reservation_expired_or_invalid','status'=>409];
            }

            $tariff = Tariff::where('device_channel_id', $channel->id)
                ->where('code', $data['tariff_id'])->where('enabled', true)->first();
            if (!$tariff) return ['error'=>'tariff_not_found','status'=>404];

            $existing = Payment::where('reservation_id', $data['reservation_id'])
                ->whereIn('status', ['pending','paid'])->first();
            if ($existing) {
                return ['payment'=>$existing,'status'=>200];
            }

            $payment = Payment::create([
                'payment_id' => 'PAY-'.Str::upper(Str::random(24)),
                'reservation_id' => $data['reservation_id'],
                'device_channel_id' => $channel->id,
                'tariff_id' => $tariff->id,
                'amount' => $tariff->price,
                'currency' => $tariff->currency,
                'status' => 'pending',
                'provider' => config('qroplate.payment_provider', 'test'),
            ]);

            $channel->update(['current_payment_id'=>$payment->payment_id]);
            return ['payment'=>$payment,'status'=>201];
        });

        $status = $result['status'];
        if (isset($result['error'])) {
            $error = $result['error']; unset($result['status']);
            return response()->json(['error'=>$error], $status);
        }

        $payment = $result['payment'];
        return response()->json([
            'payment_id'=>$payment->payment_id,
            'status'=>$payment->status,
            'amount'=>(float)$payment->amount,
            'currency'=>$payment->currency,
            'provider'=>$payment->provider,
            'test_pay_url'=>config('qroplate.payment_provider') === 'test' ? route('api.test-payments.pay', $payment->payment_id) : null,
        ], $status);
    }

    public function status(string $paymentId): JsonResponse
    {
        $payment = Payment::with('session')->where('payment_id', $paymentId)->firstOrFail();
        return response()->json([
            'payment_id'=>$payment->payment_id,
            'status'=>$payment->status,
            'paid_at'=>optional($payment->paid_at)?->toIso8601String(),
            'session_id'=>optional($payment->session)->session_id,
        ]);
    }

    public function testPay(string $paymentId, PaymentSessionService $service): JsonResponse
    {
        abort_unless(config('qroplate.payment_provider') === 'test', 404);
        $payment = Payment::where('payment_id', $paymentId)->firstOrFail();
        $session = $service->markPaidAndCreateSession($payment);

        return response()->json([
            'payment_id'=>$payment->payment_id,
            'status'=>'paid',
            'session_id'=>$session->session_id,
        ]);
    }
}

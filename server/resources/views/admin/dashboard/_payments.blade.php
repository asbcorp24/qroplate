<div class="card shadow-sm border-0 h-100">
    <div class="card-header bg-white"><strong>Последние платежи</strong></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>ID</th><th>Прибор</th><th>Канал</th><th>Сумма</th><th>Статус</th></tr></thead>
        <tbody>
        @forelse($recentPayments as $payment)
            <tr>
                <td class="small">{{ $payment->payment_id }}</td>
                <td>{{ optional(optional($payment->channel)->device)->name ?? '—' }}</td>
                <td>R{{ optional($payment->channel)->channel ?? '—' }}</td>
                <td>{{ number_format((float)$payment->amount,2,',',' ') }} {{ $payment->currency }}</td>
                <td><span class="badge text-bg-{{ $payment->status === 'paid' ? 'success' : 'secondary' }}">{{ $payment->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Платежей пока нет</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

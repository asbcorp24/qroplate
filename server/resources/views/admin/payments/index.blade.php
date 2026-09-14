@extends('layouts.admin')
@section('title','Платежи — QROplate')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Платежи</h1><div class="text-muted">История платежей по доступным вам приборам</div></div><a class="btn btn-outline-success" href="{{ route('admin.payments.csv', request()->query()) }}">CSV</a></div>
<form class="card card-body shadow-sm border-0 mb-3" method="GET"><div class="row g-2">
<div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
<div class="col-md-2"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
@if(session('admin_role')==='super')<div class="col-md-2"><select name="admin_id" class="form-select"><option value="">Все админы</option>@foreach($admins as $a)<option value="{{ $a->id }}" @selected(request('admin_id')==$a->id)>{{ $a->name }}</option>@endforeach</select></div>@endif
<div class="col-md-2"><select name="device_id" class="form-select"><option value="">Все приборы</option>@foreach($devices as $d)<option value="{{ $d->id }}" @selected(request('device_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
<div class="col-md-1"><select name="channel" class="form-select"><option value="">R*</option>@for($i=1;$i<=4;$i++)<option value="{{ $i }}" @selected(request('channel')==$i)>R{{ $i }}</option>@endfor</select></div>
<div class="col-md-2"><select name="status" class="form-select"><option value="">Все статусы</option>@foreach(['pending','paid','failed','cancelled','refunded'] as $s)<option value="{{ $s }}" @selected(request('status')==$s)>{{ strtoupper($s) }}</option>@endforeach</select></div>
<div class="col-md-1"><button class="btn btn-primary w-100">OK</button></div></div></form>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Дата</th><th>Payment ID</th><th>Прибор</th><th>Канал</th><th>Тариф</th><th>Сумма</th><th>Статус</th><th>Провайдер</th></tr></thead><tbody>
@forelse($payments as $p)<tr><td>{{ optional($p->created_at)->format('d.m.Y H:i') }}</td><td><code>{{ $p->payment_id }}</code></td><td>{{ optional(optional($p->channel)->device)->name }}</td><td>R{{ optional($p->channel)->channel }}</td><td>{{ optional($p->tariff)->title }}</td><td>{{ number_format((float)$p->amount,2,',',' ') }} {{ $p->currency }}</td><td><span class="badge text-bg-{{ $p->status==='paid'?'success':($p->status==='failed'?'danger':'secondary') }}">{{ strtoupper($p->status) }}</span></td><td>{{ $p->provider }}</td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">Платежей нет</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $payments->links() }}</div>
@endsection

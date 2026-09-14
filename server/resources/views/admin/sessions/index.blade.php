@extends('layouts.admin')
@section('title','Сессии — QROplate')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Сессии</h1><div class="text-muted">История запусков каналов</div></div><a class="btn btn-outline-success" href="{{ route('admin.sessions.csv', request()->query()) }}">CSV</a></div>
<form class="card card-body shadow-sm border-0 mb-3" method="GET"><div class="row g-2">
<div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
<div class="col-md-2"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
@if(session('admin_role')==='super')<div class="col-md-2"><select name="admin_id" class="form-select"><option value="">Все админы</option>@foreach($admins as $a)<option value="{{ $a->id }}" @selected(request('admin_id')==$a->id)>{{ $a->name }}</option>@endforeach</select></div>@endif
<div class="col-md-2"><select name="device_id" class="form-select"><option value="">Все приборы</option>@foreach($devices as $d)<option value="{{ $d->id }}" @selected(request('device_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
<div class="col-md-1"><select name="channel" class="form-select"><option value="">R*</option>@for($i=1;$i<=4;$i++)<option value="{{ $i }}" @selected(request('channel')==$i)>R{{ $i }}</option>@endfor</select></div>
<div class="col-md-2"><select name="status" class="form-select"><option value="">Все статусы</option>@foreach(['pending','running','finished','error','cancelled'] as $s)<option value="{{ $s }}" @selected(request('status')==$s)>{{ strtoupper($s) }}</option>@endforeach</select></div>
<div class="col-md-1"><button class="btn btn-primary w-100">OK</button></div></div></form>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Дата</th><th>Session ID</th><th>Прибор</th><th>Канал</th><th>Payment ID</th><th>Время</th><th>Статус</th><th>Период</th></tr></thead><tbody>
@forelse($sessions as $s)<tr><td>{{ optional($s->created_at)->format('d.m.Y H:i') }}</td><td><code>{{ $s->session_id }}</code></td><td>{{ optional(optional($s->channel)->device)->name }}</td><td>R{{ optional($s->channel)->channel }}</td><td>{{ optional($s->payment)->payment_id }}</td><td>{{ gmdate('H:i:s',(int)$s->duration_sec) }}</td><td><span class="badge text-bg-{{ $s->status==='running'?'primary':($s->status==='finished'?'success':($s->status==='error'?'danger':'secondary')) }}">{{ strtoupper($s->status) }}</span></td><td class="small">{{ optional($s->started_at)->format('d.m H:i:s') }}<br>{{ optional($s->ends_at)->format('d.m H:i:s') }}</td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">Сессий нет</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $sessions->links() }}</div>
@endsection

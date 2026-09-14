@extends('layouts.admin')
@section('title','Отчёты — QROplate')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-1">Отчёты</h1><div class="text-muted">Сводка по обороту и запускам</div></div><a class="btn btn-outline-success" href="{{ route('admin.reports.csv', request()->query()) }}">CSV / Excel</a></div>
<form class="card card-body shadow-sm border-0 mb-3" method="GET"><div class="row g-2">
<div class="col-md-2"><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
<div class="col-md-2"><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
@if($isSuper)<div class="col-md-2"><select name="admin_id" class="form-select"><option value="">Все админы</option>@foreach($admins as $a)<option value="{{ $a->id }}" @selected(request('admin_id')==$a->id)>{{ $a->name }}</option>@endforeach</select></div>@endif
<div class="col-md-3"><select name="device_id" class="form-select"><option value="">Все приборы</option>@foreach($devices as $d)<option value="{{ $d->id }}" @selected(request('device_id')==$d->id)>{{ $d->name }}</option>@endforeach</select></div>
<div class="col-md-1"><select name="channel" class="form-select"><option value="">R*</option>@for($i=1;$i<=4;$i++)<option value="{{ $i }}" @selected(request('channel')==$i)>R{{ $i }}</option>@endfor</select></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Сформировать</button></div></div></form>
@php($totalHours = intdiv((int)$summary['duration_sec'],3600))
@php($totalMinutes = intdiv(((int)$summary['duration_sec'])%3600,60))
<div class="row g-3 mb-4">
<div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Оборот</div><div class="h4 mb-0">{{ number_format($summary['revenue'],2,',',' ') }} ₽</div></div></div></div>
<div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Платежи</div><div class="h4 mb-0">{{ $summary['payments'] }}</div></div></div></div>
<div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Сессии</div><div class="h4 mb-0">{{ $summary['sessions'] }}</div></div></div></div>
<div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Работают</div><div class="h4 mb-0 text-primary">{{ $summary['running'] }}</div></div></div></div>
<div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Завершено</div><div class="h4 mb-0 text-success">{{ $summary['finished'] }}</div></div></div></div>
<div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Оплачено времени</div><div class="h4 mb-0">{{ $totalHours }} ч {{ $totalMinutes }} мин</div></div></div></div>
</div>
<div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Прибор</th><th>Оборот</th><th>Платежи</th><th>Сессии</th><th>Время</th></tr></thead><tbody>
@forelse($rows as $row)@php($h=intdiv((int)$row['duration_sec'],3600)) @php($m=intdiv(((int)$row['duration_sec'])%3600,60))<tr><td><strong>{{ $row['device']->name }}</strong><div class="small text-muted">{{ $row['device']->device_id }}</div></td><td>{{ number_format($row['revenue'],2,',',' ') }} ₽</td><td>{{ $row['payments'] }}</td><td>{{ $row['sessions'] }}</td><td>{{ $h }} ч {{ $m }} мин</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">За выбранный период данных нет</td></tr>@endforelse
</tbody></table></div></div>
@endsection

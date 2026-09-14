@extends('layouts.admin')
@section('title','Дашборд — QROplate')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Дашборд</h1>
        <div class="text-muted">{{ $isSuper ? 'Общая статистика системы' : 'Статистика ваших приборов' }}</div>
    </div>
    <a href="{{ route('admin.devices.create') }}" class="btn btn-primary">+ Добавить прибор</a>
</div>

<div class="row g-3 mb-4">
    @if($isSuper)
    <div class="col-6 col-xl-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Администраторы</div><div class="display-6 fw-semibold">{{ $adminCount }}</div></div></div></div>
    @endif
    <div class="col-6 col-xl-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Приборы</div><div class="display-6 fw-semibold">{{ $deviceCount }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Каналы</div><div class="display-6 fw-semibold">{{ $channelCount }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Свободно</div><div class="display-6 fw-semibold text-success">{{ $statusCounts['free'] }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Работают</div><div class="display-6 fw-semibold text-primary">{{ $statusCounts['running'] }}</div></div></div></div>
    <div class="col-6 col-xl-2"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">В резерве</div><div class="display-6 fw-semibold text-warning">{{ $statusCounts['reserved'] }}</div></div></div></div>
</div>

@include('admin.dashboard._finance')

<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="card shadow-sm border-0"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small">Ошибки каналов</div><div class="h2 mb-0 text-danger">{{ $statusCounts['error'] }}</div></div><span class="badge text-bg-danger">ERROR</span></div></div></div>
    <div class="col-md-6"><div class="card shadow-sm border-0"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="text-muted small">Отключены</div><div class="h2 mb-0 text-secondary">{{ $statusCounts['disabled'] }}</div></div><span class="badge text-bg-secondary">DISABLED</span></div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-6">@include('admin.dashboard._payments')</div>
    <div class="col-xl-6">@include('admin.dashboard._sessions')</div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white"><strong>Быстрый переход</strong></div>
    <div class="card-body d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="{{ route('admin.devices.index') }}">Все приборы</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.devices.create') }}">Добавить прибор</a>
        @if($isSuper)<a class="btn btn-outline-dark" href="{{ route('admin.admins.index') }}">Администраторы</a>@endif
    </div>
</div>
@endsection

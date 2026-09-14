@extends('layouts.admin')
@section('title','Устройства — QROplate')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-1">Устройства</h1>
        <div class="text-muted">Состояние четырёх каналов каждого контроллера</div>
    </div>
    <a href="{{ route('admin.devices.create') }}" class="btn btn-primary">Добавить прибор</a>
</div>
<div class="row g-3">
@foreach($devices as $device)
    <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">{{ $device->title ?: $device->name }}</h2>
                        <div class="small text-muted">{{ $device->device_id }} @if($device->location) · {{ $device->location }} @endif</div>
                        @if(session('admin_role') === 'super')
                            <div class="small mt-1">Владелец: <strong>{{ optional($device->owner)->name ?: 'Супер админ' }}</strong></div>
                        @endif
                    </div>
                    <a href="{{ route('admin.devices.edit',$device) }}" class="btn btn-outline-primary btn-sm">Настроить</a>
                </div>
                <div class="row g-2">
                    @foreach($device->channels->sortBy('channel') as $channel)
                        @php($status=$channel->effectiveStatus())
                        @php($class=['free'=>'success','reserved'=>'warning','running'=>'primary','error'=>'danger','disabled'=>'secondary'][$status] ?? 'secondary')
                        <div class="col-6">
                            <div class="border rounded p-3 bg-white">
                                <div class="d-flex justify-content-between"><strong>R{{ $channel->channel }}</strong><span class="badge text-bg-{{ $class }}">{{ strtoupper($status) }}</span></div>
                                <div class="mt-2">{{ $channel->name }}</div>
                                @if($channel->occupied_until)<div class="small text-muted mt-1">до {{ $channel->occupied_until->format('d.m H:i:s') }}</div>
                                @elseif($channel->reserved_until)<div class="small text-muted mt-1">резерв до {{ $channel->reserved_until->format('H:i:s') }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection

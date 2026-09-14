@extends('layouts.admin')
@section('title','Настройка устройства')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $device->title ?: $device->name }}</h1>
        <div class="text-muted">{{ $device->device_id }}</div>
    </div>
    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Назад</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Карточка устройства в приложении</div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.devices.update',$device) }}" class="row g-3">
            @csrf @method('PUT')
            <div class="col-md-4"><label class="form-label">Служебное имя</label><input name="name" class="form-control" value="{{ old('name',$device->name) }}" required></div>
            <div class="col-md-4"><label class="form-label">Тип</label><input name="type" class="form-control" value="{{ old('type',$device->type) }}" required></div>
            <div class="col-md-4"><label class="form-label">Место</label><input name="location" class="form-control" value="{{ old('location',$device->location) }}"></div>
            <div class="col-md-6"><label class="form-label">Заголовок</label><input name="title" class="form-control" value="{{ old('title',$device->title) }}"></div>
            <div class="col-md-6"><label class="form-label">Подзаголовок</label><input name="subtitle" class="form-control" value="{{ old('subtitle',$device->subtitle) }}"></div>
            <div class="col-12"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="3">{{ old('description',$device->description) }}</textarea></div>
            <div class="col-md-6"><label class="form-label">URL изображения</label><input name="image_url" class="form-control" value="{{ old('image_url',$device->image_url) }}"></div>
            <div class="col-md-2"><label class="form-label">Цвет</label><input name="accent_color" class="form-control" value="{{ old('accent_color',$device->accent_color) }}"></div>
            <div class="col-md-2"><label class="form-label">Иконка</label><input name="icon" class="form-control" value="{{ old('icon',$device->icon) }}"></div>
            <div class="col-md-2"><label class="form-label">Layout</label><input name="layout" class="form-control" value="{{ old('layout',$device->layout) }}"></div>
            <div class="col-12"><label class="form-label">Сообщение обслуживания</label><input name="maintenance_message" class="form-control" value="{{ old('maintenance_message',$device->maintenance_message) }}"></div>
            <div class="col-12"><div class="form-check"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled" @checked($device->enabled)><label class="form-check-label" for="enabled">Устройство активно</label></div></div>
            <div class="col-12"><button class="btn btn-primary">Сохранить устройство</button></div>
        </form>
    </div>
</div>

<div class="row g-3">
@foreach($device->channels as $channel)
<div class="col-12 col-xl-6">
    <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Relay {{ $channel->channel }}</span>
            @php($status=$channel->effectiveStatus())
            <span class="badge text-bg-{{ $status==='free'?'success':($status==='running'?'primary':($status==='reserved'?'warning':($status==='error'?'danger':'secondary'))) }}">{{ strtoupper($status) }}</span>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.devices.channels.update',[$device,$channel]) }}" class="row g-2 mb-4">
                @csrf @method('PUT')
                <div class="col-8"><input name="name" class="form-control" value="{{ $channel->name }}" required></div>
                <div class="col-4 d-flex align-items-center"><div class="form-check"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" name="enabled" value="1" @checked($channel->enabled)><label class="form-check-label">Включён</label></div></div>
                <div class="col-12"><button class="btn btn-outline-primary btn-sm">Сохранить канал</button></div>
            </form>

            <h3 class="h6">Тарифы</h3>
            <div class="table-responsive mb-3"><table class="table table-sm align-middle"><thead><tr><th>Название</th><th>Время</th><th>Цена</th></tr></thead><tbody>
            @forelse($channel->tariffs as $tariff)
                <tr><td>{{ $tariff->title }}<div class="small text-muted">{{ $tariff->code }}</div></td><td>{{ intdiv($tariff->duration_sec,60) }} мин</td><td>{{ $tariff->price }} {{ $tariff->currency }}</td></tr>
            @empty<tr><td colspan="3" class="text-muted">Нет тарифов</td></tr>@endforelse
            </tbody></table></div>

            <form method="post" action="{{ route('admin.devices.channels.tariffs.store',[$device,$channel]) }}" class="row g-2 border-top pt-3">
                @csrf
                <div class="col-md-6"><input name="code" class="form-control form-control-sm" placeholder="TARIFF-R{{ $channel->channel }}-15" required></div>
                <div class="col-md-6"><input name="title" class="form-control form-control-sm" placeholder="15 минут" required></div>
                <div class="col-md-4"><input name="duration_sec" type="number" class="form-control form-control-sm" placeholder="900" required></div>
                <div class="col-md-4"><input name="price" type="number" step="0.01" class="form-control form-control-sm" placeholder="100.00" required></div>
                <div class="col-md-4"><input name="currency" class="form-control form-control-sm" value="RUB" maxlength="3" required></div>
                <div class="col-12"><button class="btn btn-success btn-sm">Добавить тариф</button></div>
            </form>
        </div>
    </div>
</div>
@endforeach
</div>
@endsection

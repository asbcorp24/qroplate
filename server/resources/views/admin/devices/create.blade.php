@extends('layouts.admin')
@section('title','Новый прибор — QROplate')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Новый прибор</h3>
    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">Назад</a>
</div>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.devices.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Device ID</label><input name="device_id" class="form-control" placeholder="DEV-000002" required></div>
                <div class="col-md-6"><label class="form-label">Название</label><input name="name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Тип</label><input name="type" class="form-control" value="timed_relay" required></div>
                <div class="col-md-6"><label class="form-label">Место установки</label><input name="location" class="form-control"></div>
                <div class="col-12"><label class="form-label">Заголовок в приложении</label><input name="title" class="form-control"></div>
            </div>
            <button class="btn btn-primary mt-4">Создать прибор и 4 канала</button>
        </form>
    </div>
</div>
@endsection

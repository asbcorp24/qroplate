@extends('layouts.admin')
@section('title','Администраторы — QROplate')
@section('content')
<h1 class="h3 mb-3">Администраторы</h1>
<div class="card shadow-sm mb-4"><div class="card-body">
<form method="POST" action="{{ route('admin.admins.store') }}" class="row g-2">@csrf
<div class="col-md-4"><input name="name" class="form-control" placeholder="Имя" required></div>
<div class="col-md-3"><input name="login" class="form-control" placeholder="Логин" required></div>
<div class="col-md-3"><input name="password" type="password" class="form-control" placeholder="Пароль" required></div>
<div class="col-md-2"><button class="btn btn-primary w-100">Добавить</button></div>
</form></div></div>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0">
<thead><tr><th>Имя</th><th>Логин</th><th>Приборов</th><th>Статус</th><th>Последний вход</th></tr></thead><tbody>
@foreach($admins as $admin)<tr><td>{{ $admin->name }}</td><td>{{ $admin->login }}</td><td>{{ $admin->devices_count }}</td><td>{{ $admin->enabled ? 'Активен' : 'Отключён' }}</td><td>{{ optional($admin->last_login_at)->format('d.m.Y H:i') ?: '—' }}</td></tr>@endforeach
</tbody></table></div></div>
@endsection

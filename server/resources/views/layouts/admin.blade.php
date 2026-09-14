<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'QROplate')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">QROplate Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="adminNav">
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="{{ route('admin.dashboard') }}">Дашборд</a>
                <a class="nav-link" href="{{ route('admin.devices.index') }}">Приборы</a>
                <a class="nav-link" href="{{ route('admin.payments.index') }}">Платежи</a>
                <a class="nav-link" href="{{ route('admin.sessions.index') }}">Сессии</a>
                <a class="nav-link" href="{{ route('admin.reports.index') }}">Отчёты</a>
                @if(session('admin_role') === 'super')
                    <a class="nav-link" href="{{ route('admin.admins.index') }}">Администраторы</a>
                @endif
            </div>
            <div class="d-flex align-items-center gap-3 text-white">
                <div class="small text-end">
                    <div>{{ session('admin_name') }}</div>
                    <div class="text-white-50">{{ session('admin_role') === 'super' ? 'SUPER ADMIN' : 'ADMIN' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-light btn-sm">Выйти</button></form>
            </div>
        </div>
    </div>
</nav>
<div class="container-fluid px-4 pb-5">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @yield('content')
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>

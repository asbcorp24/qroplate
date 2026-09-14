<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Оборот всего</div><div class="h3 mb-0">{{ number_format((float)$revenueTotal,2,',',' ') }} ₽</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Оборот сегодня</div><div class="h3 mb-0">{{ number_format((float)$revenueToday,2,',',' ') }} ₽</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Оплачено</div><div class="h3 mb-0">{{ $paidCount }}</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card shadow-sm border-0 h-100"><div class="card-body"><div class="text-muted small">Активные сессии</div><div class="h3 mb-0 text-primary">{{ $activeSessions }}</div></div></div></div>
</div>

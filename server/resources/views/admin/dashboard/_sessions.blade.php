<div class="card shadow-sm border-0 h-100">
    <div class="card-header bg-white"><strong>Последние сессии</strong></div>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Session</th><th>Прибор</th><th>Канал</th><th>Время</th><th>Статус</th></tr></thead>
        <tbody>
        @forelse($recentSessions as $item)
            <tr>
                <td class="small">{{ $item->session_id }}</td>
                <td>{{ optional(optional($item->channel)->device)->name ?? '—' }}</td>
                <td>R{{ optional($item->channel)->channel ?? '—' }}</td>
                <td>{{ gmdate('H:i:s',(int)$item->duration_sec) }}</td>
                <td><span class="badge text-bg-{{ in_array($item->status,['active','running']) ? 'primary' : 'secondary' }}">{{ $item->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Сессий пока нет</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

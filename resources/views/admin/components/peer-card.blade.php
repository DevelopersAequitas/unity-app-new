<div class="peer-card">
    <div class="peer-name fw-semibold text-truncate" style="max-width: {{ $maxWidth ?? 220 }}px;">
        @if(!empty($userId))
            <a href="#" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $userId }}', event);">
                {{ ($name ?? '') !== '' ? $name : '—' }}
            </a>
        @else
            {{ ($name ?? '') !== '' ? $name : '—' }}
        @endif
    </div>
    <div class="small text-muted">{{ ($company ?? '') !== '' ? $company : '—' }}</div>
    <div class="small text-muted">{{ ($city ?? '') !== '' ? $city : 'No City' }}</div>
</div>

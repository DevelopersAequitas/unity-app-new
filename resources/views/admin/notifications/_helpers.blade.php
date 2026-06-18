@php
    $userName = function ($user) {
        if (! $user) return '-';
        return $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->email ?? 'User');
    };
    $statusBadge = fn ($status) => match ($status) { 'delivered', 'sent', 'completed' => 'success', 'failed' => 'danger', 'queued' => 'primary', 'pending', 'running' => 'secondary', default => 'info' };
    $priorityBadge = fn ($priority) => match ($priority) { 'urgent' => 'danger', 'high' => 'warning', 'medium' => 'primary', 'low' => 'secondary', default => 'light' };
@endphp

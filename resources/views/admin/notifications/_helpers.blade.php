@once
    @php
        if (! function_exists('notification_admin_user_name')) {
            function notification_admin_user_name($user): string {
                if (! $user) { return 'Unknown User'; }
                $displayName = trim((string) ($user->display_name ?? '')) ?: trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? '')));
                return $displayName !== '' ? $displayName : (string) ($user->name ?? $user->email ?? $user->phone ?? 'Unknown User');
            }
        }
        if (! function_exists('notification_admin_status_badge')) {
            function notification_admin_status_badge($status): string {
                return match ((string) $status) { 'delivered', 'sent', 'completed' => 'success', 'failed' => 'danger', 'queued' => 'primary', 'pending', 'running' => 'secondary', default => 'info' };
            }
        }
        if (! function_exists('notification_admin_priority_badge')) {
            function notification_admin_priority_badge($priority): string {
                return match ((string) $priority) { 'urgent' => 'danger', 'high' => 'warning', 'medium' => 'primary', 'low' => 'secondary', default => 'secondary' };
            }
        }
        if (! function_exists('notification_admin_json')) {
            function notification_admin_json($value): string {
                return json_encode($value ?: new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            }
        }
    @endphp
@endonce

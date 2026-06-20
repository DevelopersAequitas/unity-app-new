@extends('admin.layouts.app')
@section('title', 'Send Notification')
@section('content')
@include('admin.notifications._helpers')
@include('admin.notifications._styles')
@include('admin.notifications._flash')
@if (session('warning'))<div class="alert alert-warning shadow-sm"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('warning') }}</div>@endif
@php($firebaseDiagnostics = $firebaseDiagnostics ?? [])
<div class="notification-page"><div class="notification-hero mb-4 d-flex flex-wrap justify-content-between gap-3"><div><h1 class="h3 mb-1">Send Notification</h1><p class="text-muted mb-0">Send an in-app notification and optional Firebase push notification to selected users.</p></div><a href="{{ route('admin.notifications.logs') }}" class="btn btn-outline-primary"><i class="bi bi-clock-history me-1"></i>View Delivery Logs</a></div>
<div class="row g-3 mb-4"><div class="col-lg-4"><div class="notification-card p-3 h-100"><div class="d-flex justify-content-between"><h6>Firebase Status</h6><span class="badge bg-{{ ($firebaseDiagnostics['file_readable'] ?? false) ? 'success' : 'warning text-dark' }}">{{ ($firebaseDiagnostics['file_readable'] ?? false) ? 'Ready' : 'Not Ready' }}</span></div><div class="small text-muted">Project ID</div><div class="fw-semibold mb-2">{{ $firebaseDiagnostics['project_id'] ?: 'Not configured' }}</div><div class="small">Credentials configured: <strong>{{ ($firebaseDiagnostics['credentials_configured'] ?? false) ? 'Yes' : 'No' }}</strong></div><div class="small">File readable: <strong>{{ ($firebaseDiagnostics['file_readable'] ?? false) ? 'Yes' : 'No' }}</strong></div></div></div><div class="col-lg-4"><div class="notification-card p-3 h-100"><h6>Push Delivery Requirement</h6><p class="text-muted mb-0">Push requires a valid Firebase device token. Users without an active token can still receive in-app notifications.</p></div></div><div class="col-lg-4"><div class="notification-card p-3 h-100"><h6>Local Testing Note</h6><p class="text-muted mb-0">Local Laravel can work with Firebase, but the mobile app must call a reachable backend URL such as LAN IP, ngrok, or live URL. 127.0.0.1 will not work from a physical phone.</p></div></div></div>
<form method="POST" action="{{ route('admin.notifications.send-test.store') }}" id="send_test_notification_form">@csrf<div class="row g-4"><div class="col-xl-5"><div class="notification-card p-4 mb-4"><h5>Recipient Selection</h5><p class="text-muted">Choose who should receive this notification.</p><div class="row g-3"><div class="col-md-5"><label class="form-label">Filter by active push token</label><select id="push_token_status_filter" class="form-select"><option value="all">All users</option><option value="with">Users with active push token</option><option value="without">Users without active push token</option></select></div><div class="col-md-7"><label class="form-label" for="notification_user_id">User *</label><select name="user_id" id="notification_user_id" class="form-control select2-peer" required><option value="">Select peer</option></select><div id="push-token-help" class="form-text">Open the dropdown to load peers.</div></div><div class="col-12"><div id="selected-user-push-status" class="border rounded-3 p-3 bg-light small d-none"><div class="fw-semibold mb-2">Selected User Preview</div><div class="row g-2"><div class="col-md-4">Active tokens: <strong data-field="active_tokens">0</strong></div><div class="col-md-4">Inactive tokens: <strong data-field="inactive_tokens">0</strong></div><div class="col-md-4">Can send push: <strong data-field="can_send_push">No</strong></div><div class="col-md-4">Platform: <strong data-field="latest_platform">-</strong></div><div class="col-md-4">Last used: <strong data-field="latest_last_used_at">-</strong></div><div class="col-md-12 text-muted">Last failure: <span data-field="last_failure_reason">-</span></div></div></div></div></div></div><div class="notification-card p-4"><h5>Send Options</h5><div class="alert alert-warning small">Push may fail for users with invalid or expired tokens.</div><div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Send Notification</button><a href="{{ route('admin.notifications.send-test') }}" class="btn btn-outline-secondary">Reset</a></div></div></div><div class="col-xl-7"><div class="notification-card p-4"><h5>Compose Message</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Title *</label><input name="title" class="form-control" value="{{ old('title', request('title', 'Test Notification')) }}" required maxlength="255"></div><div class="col-md-6"><label class="form-label">Tap destination</label><input name="screen" class="form-control" value="{{ old('screen', request('screen', 'home')) }}" required></div><div class="col-md-6"><label class="form-label">Type / Category *</label><input name="type" class="form-control" value="{{ old('type', request('type', 'test_notification')) }}" required></div><div class="col-md-6"><label class="form-label">Category</label><input name="category" class="form-control" value="{{ old('category', 'admin_test') }}"></div><div class="col-md-6"><label class="form-label">Priority *</label><select name="priority" class="form-select" required>@foreach(['low','medium','high','urgent'] as $p)<option value="{{ $p }}" @selected(old('priority','high')===$p)>{{ notification_admin_label($p) }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Channel *</label><select name="channel" class="form-select" required><option value="in_app_only">In-app only</option><option value="push" @selected(old('channel','push')==='push')>In-app + Push</option><option value="push_email" @selected(old('channel')==='push_email')>Push + Email</option><option value="email" @selected(old('channel')==='email')>Email</option></select></div><div class="col-md-7"><label class="form-label">Body *</label><textarea name="body" class="form-control" rows="6" required>{{ old('body', request('body', 'This is a test push notification from Unity admin.')) }}</textarea></div><div class="col-md-5"><div class="notification-preview-phone"><div class="small opacity-75 mb-2">Mobile Preview</div><div class="notification-preview-bubble"><div class="fw-bold">PeersGlobal Unity</div><div class="fw-semibold mt-2">{{ old('title', request('title', 'Notification title')) }}</div><div class="small text-muted mt-1">{{ old('body', request('body', 'Notification body preview appears here.')) }}</div><div class="small text-primary mt-2">Tap opens: {{ old('screen', request('screen', 'home')) }}</div></div></div></div><div class="col-md-6"><label class="form-label">Reference Type</label><input name="reference_type" class="form-control" value="{{ old('reference_type') }}"></div><div class="col-md-6"><label class="form-label">Reference ID</label><input name="reference_id" class="form-control" value="{{ old('reference_id') }}"></div><div class="col-12"><label class="form-label">Data JSON</label><textarea name="data" class="form-control font-monospace" rows="3">{{ old('data', json_encode(['test' => true], JSON_PRETTY_PRINT)) }}</textarea></div></div></div></div></div></form>
<div class="notification-card overflow-hidden mt-4"><div class="p-3 border-bottom fw-semibold">Recent Manual Notifications</div><div class="table-responsive"><table class="table table-hover align-middle notification-admin-table mb-0"><thead class="table-light"><tr><th>Date</th><th>User</th><th>Notification</th><th>Channel</th><th>Status</th><th>Failure Reason</th><th>Sent</th><th>Read</th><th>Clicked</th></tr></thead><tbody>@forelse($recentNotifications as $notification)<tr><td>{{ optional($notification->created_at)->format('d M Y H:i') ?? '—' }}</td><td>{{ notification_admin_user_name($notification->user) }}</td><td><div class="fw-semibold">{{ $notification->title ?? '—' }}</div><div class="notification-mono">{{ notification_admin_label($notification->type) }}</div></td><td>{{ notification_admin_channel_label($notification->channel) }}</td><td><span class="badge bg-{{ notification_admin_status_badge($notification->status) }}">{{ notification_admin_label($notification->status) }}</span></td><td title="{{ $notification->failure_reason }}">{{ Str::limit(notification_admin_error_summary($notification->failure_reason), 60) }}</td><td>{{ optional($notification->sent_at)->format('d M H:i') ?? '—' }}</td><td>{{ optional($notification->read_at)->format('d M H:i') ?? '—' }}</td><td>{{ optional($notification->clicked_at)->format('d M H:i') ?? '—' }}</td></tr>@empty<tr><td colspan="9"><div class="notification-empty">No manual notifications sent yet.</div></td></tr>@endforelse</tbody></table></div></div></div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !jQuery.fn.select2) {
            return;
        }

        jQuery('#notification_user_id').select2({
            placeholder: 'Select peer',
            allowClear: true,
            minimumInputLength: 0,
            width: '100%',
            ajax: {
                url: '{{ route('admin.notifications.users.search') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1,
                        push_token_status: jQuery('#push_token_status_filter').val() || 'all'
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results || [],
                        pagination: { more: data.pagination ? data.pagination.more : false }
                    };
                },
                cache: true
            },
            templateResult: function (peer) {
                if (!peer.id) {
                    return peer.text || 'Select peer';
                }
                const count = Number(peer.active_push_tokens_count || 0);
                const badgeClass = count > 0 ? 'bg-success' : 'bg-warning text-dark';
                const badgeText = count > 0 ? 'Push ready' : 'No device token';
                return jQuery('<div class="d-flex justify-content-between align-items-center gap-2"><span></span><span class="badge ' + badgeClass + '">' + badgeText + '</span></div>').find('span:first').text(peer.text || '').end();
            },
            templateSelection: function (peer) {
                return peer.text || 'Select peer';
            }
        }).on('select2:select', function (event) {
            const count = Number(event.params.data.active_push_tokens_count || 0);
            this.dataset.activePushTokens = String(count);
            const help = document.getElementById('push-token-help');
            if (help) {
                help.textContent = count > 0 ? `Push ready (${count} active device token${count === 1 ? '' : 's'})` : 'No device token — the user has not opened the app with notification permission, or the old Firebase token became invalid.';
                help.className = count > 0 ? 'form-text text-success' : 'form-text text-warning';
            }
            loadPushStatus(event.params.data.id);
        });


        function loadPushStatus(userId) {
            const panel = document.getElementById('selected-user-push-status');
            if (!panel || !userId) {
                return;
            }
            const url = '{{ route('admin.notifications.users.push-status', ['user' => '__USER__']) }}'.replace('__USER__', userId);
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then((response) => response.json())
                .then((payload) => {
                    const data = payload.data || {};
                    panel.classList.remove('d-none');
                    panel.querySelector('[data-field="active_tokens"]').textContent = data.active_tokens ?? 0;
                    panel.querySelector('[data-field="inactive_tokens"]').textContent = data.inactive_tokens ?? 0;
                    panel.querySelector('[data-field="can_send_push"]').textContent = data.can_send_push ? 'Yes' : 'No';
                    panel.querySelector('[data-field="latest_platform"]').textContent = data.latest_platform || '-';
                    panel.querySelector('[data-field="latest_last_used_at"]').textContent = data.latest_last_used_at || '-';
                    panel.querySelector('[data-field="last_failure_reason"]').textContent = data.last_failure_reason || '-';
                })
                .catch(() => panel.classList.add('d-none'));
        }

        document.getElementById('send_test_notification_form')?.addEventListener('submit', function (event) {
            const channel = this.querySelector('[name="channel"]')?.value;
            const count = Number(document.getElementById('notification_user_id')?.dataset.activePushTokens || 0);
            if ((channel === 'push' || channel === 'push_email') && count === 0) {
                if (!confirm('Selected user has no valid Firebase device token. Push will fail until Flutter registers a fresh token. Continue?')) {
                    event.preventDefault();
                }
            }
        });

        jQuery('#push_token_status_filter').on('change', function () {
            jQuery('#notification_user_id').val(null).trigger('change');
            const help = document.getElementById('push-token-help');
            if (help) {
                help.textContent = 'Open the dropdown to load peers.';
                help.className = 'form-text';
            }
            document.getElementById('selected-user-push-status')?.classList.add('d-none');
        });
    });
</script>
@endpush

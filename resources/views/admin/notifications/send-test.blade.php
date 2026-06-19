@extends('admin.layouts.app')
@section('title', 'Send Test Notification')

@section('content')
@include('admin.notifications._helpers')
@include('admin.notifications._styles')
@include('admin.notifications._flash')

@if (session('warning'))
    <div class="alert alert-warning shadow-sm"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('warning') }}</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0">Send Test Notification</h1>
        <div class="text-muted small">Create an in-app notification and optionally deliver push/email.</div>
    </div>
</div>

@php($firebaseDiagnostics = $firebaseDiagnostics ?? [])
<div class="card shadow-sm mb-4 border-{{ ($firebaseDiagnostics['file_readable'] ?? false) ? 'success' : 'warning' }}">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Firebase Push Diagnostics</span>
        @if($firebaseDiagnostics['file_readable'] ?? false)
            <span class="badge bg-success">Ready</span>
        @else
            <span class="badge bg-warning text-dark">Check configuration</span>
        @endif
    </div>
    <div class="card-body">
        @unless($firebaseDiagnostics['file_readable'] ?? false)
            <div class="alert alert-warning mb-3">
                Firebase credentials are not ready. Push sends will be saved with a failed delivery log until the credentials path is configured and readable.
            </div>
        @endunless
        <div class="row g-3 small">
            <div class="col-md-4">
                <div class="text-muted">Project ID</div>
                <div class="fw-semibold">{{ $firebaseDiagnostics['project_id'] ?: 'Not configured' }}</div>
            </div>
            <div class="col-md-2">
                <div class="text-muted">Credentials configured</div>
                <span class="badge bg-{{ ($firebaseDiagnostics['credentials_configured'] ?? false) ? 'success' : 'danger' }}">{{ ($firebaseDiagnostics['credentials_configured'] ?? false) ? 'Yes' : 'No' }}</span>
            </div>
            <div class="col-md-2">
                <div class="text-muted">File exists</div>
                <span class="badge bg-{{ ($firebaseDiagnostics['file_exists'] ?? false) ? 'success' : 'danger' }}">{{ ($firebaseDiagnostics['file_exists'] ?? false) ? 'Yes' : 'No' }}</span>
            </div>
            <div class="col-md-2">
                <div class="text-muted">File readable</div>
                <span class="badge bg-{{ ($firebaseDiagnostics['file_readable'] ?? false) ? 'success' : 'danger' }}">{{ ($firebaseDiagnostics['file_readable'] ?? false) ? 'Yes' : 'No' }}</span>
            </div>
            <div class="col-md-12">
                <div class="text-muted">Resolved credentials path</div>
                <code class="text-break">{{ $firebaseDiagnostics['resolved_credentials_path'] ?: 'Not resolved' }}</code>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info shadow-sm">
    <strong>Push delivery requirements:</strong>
    Push notifications do not depend on login/logout status. They require a valid Firebase device token. A token is created only after the user opens the Flutter app and allows notifications at least once. If a user has <strong>No device token</strong>, push cannot be delivered to that device. You can still send <strong>In-app only</strong> notification, and the user will see it when they open the app.
</div>

<div class="alert alert-secondary shadow-sm">
    <strong>Local/mobile testing note:</strong>
    FCM push can work from a local Laravel server because Firebase is cloud-based. If testing from a physical mobile device, make sure Flutter API base URL points to a reachable backend URL, not <code>127.0.0.1</code>. Use a LAN IP such as <code>http://192.168.1.10:8000/api/v1</code>, ngrok, or a live API URL so Flutter can save the FCM token.
</div>

<div class="alert alert-light border shadow-sm">
    <strong>Flutter implementation:</strong>
    After login, request notification permission, call <code>FirebaseMessaging.instance.getToken()</code>, POST it to <code>/api/v1/notifications/push-token</code> with <code>platform</code>, <code>device_id</code>, and <code>app_version</code>, and call the same API from <code>FirebaseMessaging.instance.onTokenRefresh.listen(...)</code>. Android <code>google-services.json</code> must use project_id <code>peers-global-app</code>.
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.notifications.send-test.store') }}" id="send_test_notification_form">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="push_token_status_filter">User Filter</label>
                    <select id="push_token_status_filter" class="form-select">
                        <option value="all">All users</option>
                        <option value="with">Users with active push token</option>
                        <option value="without">Users without active push token</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="notification_user_id">User *</label>
                    <select name="user_id" id="notification_user_id" class="form-control select2-peer" required>
                        <option value="">Select peer</option>
                    </select>
                    <div id="push-token-help" class="form-text">Open the dropdown to load peers. No device token means the user has not opened the app with notification permission, or the old Firebase token became invalid.</div>
                </div>

                <div class="col-12">
                    <div id="selected-user-push-status" class="border rounded p-3 bg-light small d-none">
                        <div class="fw-semibold mb-2">Selected User Push Status</div>
                        <div class="row g-2">
                            <div class="col-md-2">Active tokens: <strong data-field="active_tokens">0</strong></div>
                            <div class="col-md-2">Inactive tokens: <strong data-field="inactive_tokens">0</strong></div>
                            <div class="col-md-2">Can send push: <strong data-field="can_send_push">No</strong></div>
                            <div class="col-md-2">Platform: <strong data-field="latest_platform">-</strong></div>
                            <div class="col-md-2">Last used: <strong data-field="latest_last_used_at">-</strong></div>
                            <div class="col-md-12 text-muted">Last failure: <span data-field="last_failure_reason">-</span></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Channel *</label>
                    <select name="channel" class="form-select" required>
                        <option value="push" @selected(old('channel', 'push') === 'push')>Push</option>
                        <option value="email" @selected(old('channel', 'push') === 'email')>Email</option>
                        <option value="push_email" @selected(old('channel', 'push') === 'push_email')>Push + Email</option>
                        <option value="in_app_only" @selected(old('channel', 'push') === 'in_app_only')>In-app only</option>
                    </select>
                    <div class="form-text">
                        Push works when a valid Firebase device token exists, even if the user is logged out. Email requires user email. Push + Email attempts both and may become partial. In-app only saves to notification history.
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Priority *</label>
                    <select name="priority" class="form-select" required>
                        <option value="low" @selected(old('priority', 'high') === 'low')>Low</option>
                        <option value="medium" @selected(old('priority', 'high') === 'medium')>Medium</option>
                        <option value="high" @selected(old('priority', 'high') === 'high')>High</option>
                        <option value="urgent" @selected(old('priority', 'high') === 'urgent')>Urgent</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Type *</label>
                    <input name="type" class="form-control" value="{{ old('type', request('type', 'test_notification')) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <input name="category" class="form-control" value="{{ old('category', 'admin_test') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Screen *</label>
                    <input name="screen" class="form-control" value="{{ old('screen', request('screen', 'home')) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Title *</label>
                    <input name="title" class="form-control" value="{{ old('title', request('title', 'Test Notification')) }}" required maxlength="255">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference Type</label>
                    <input name="reference_type" class="form-control" value="{{ old('reference_type') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference ID</label>
                    <input name="reference_id" class="form-control" value="{{ old('reference_id') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Body *</label>
                    <textarea name="body" class="form-control" rows="5" required>{{ old('body', request('body', 'This is a test push notification from Unity admin.')) }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Data JSON</label>
                    <textarea name="data" class="form-control font-monospace" rows="5">{{ old('data', json_encode(['test' => true], JSON_PRETTY_PRINT)) }}</textarea>
                </div>
            </div>

            <div class="text-end mt-3">
                <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Send Test Notification</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header">
        <strong>Recent Test Notifications</strong>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0 notification-admin-table">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Title</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Failure Reason</th>
                    <th>Sent At</th>
                    <th>Read At</th>
                    <th>Clicked At</th>
                </tr>
            </thead>

            <tbody>
                @forelse($recentNotifications as $notification)
                    <tr>
                        <td>{{ optional($notification->created_at)->format('d M Y H:i') ?? '-' }}</td>

                        <td>
                            @php
                                $user = $notification->user ?? null;
                                $displayName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

                                if ($displayName === '') {
                                    $displayName = $user->name ?? $user->email ?? 'Unknown User';
                                }
                            @endphp

                            {{ $displayName }}
                        </td>

                        <td>{{ $notification->title ?? '-' }}</td>

                        <td>{{ $notification->channel ?? '-' }}</td>

                        <td>
                            @php
                                $status = $notification->status ?? 'pending';

                                $statusClass = match ($status) {
                                    'sent' => 'success',
                                    'partial' => 'warning',
                                    'failed' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp

                            <span class="badge bg-{{ $statusClass }}">
                                {{ ucfirst($status) }}
                            </span>
                        </td>

                        <td class="text-muted small" title="{{ $notification->failure_reason ?? '' }}">
                            {{ \Illuminate\Support\Str::limit($notification->failure_reason ?? '-', 60) }}
                        </td>

                        <td>{{ optional($notification->sent_at)->format('d M H:i') ?? '-' }}</td>
                        <td>{{ optional($notification->read_at)->format('d M H:i') ?? '-' }}</td>
                        <td>{{ optional($notification->clicked_at)->format('d M H:i') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No test notifications yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

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

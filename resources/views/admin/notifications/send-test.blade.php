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

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.notifications.send-test.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="notification_user_id">User *</label>
                    <select name="user_id" id="notification_user_id" class="form-control select2-peer" required>
                        <option value="">Select peer</option>
                    </select>
                    <div id="push-token-help" class="form-text">Open the dropdown to load peers.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Channel *</label>
                    <select name="channel" class="form-select" required>
                        @foreach(['push' => 'Push', 'email' => 'Email', 'push_email' => 'Push + Email', 'in_app_only' => 'In-app only'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('channel', 'push') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Priority *</label>
                    <select name="priority" class="form-select" required>
                        @foreach(['low', 'medium', 'high', 'urgent'] as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', 'high') === $priority)>{{ Str::headline($priority) }}</option>
                        @endforeach
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

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Recent Test Notifications</div>
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle notification-admin-table mb-0">
            <thead class="table-light">
            <tr>
                <th>Date</th><th>User</th><th>Title</th><th>Channel</th><th>Status</th><th>Failure Reason</th><th>Sent At</th><th>Read At</th><th>Clicked At</th>
            </tr>
            </thead>
            <tbody>
            @forelse($recentTests as $notification)
                <tr>
                    <td>{{ optional($notification->created_at)->format('d M Y H:i') }}</td>
                    <td>{{ notification_admin_user_name($notification->user) }}</td>
                    <td>{{ $notification->title }}</td>
                    <td>{{ $notification->channel }}</td>
                    <td><span class="badge bg-{{ notification_admin_status_badge($notification->status) }}">{{ $notification->status }}</span></td>
                    <td class="text-muted small" title="{{ $notification->failure_reason }}">{{ Str::limit($notification->failure_reason, 60) ?: '-' }}</td>
                    <td>{{ optional($notification->sent_at)->format('d M H:i') ?: '-' }}</td>
                    <td>{{ optional($notification->read_at)->format('d M H:i') ?: '-' }}</td>
                    <td>{{ optional($notification->clicked_at)->format('d M H:i') ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No test notifications yet.</td></tr>
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
                    return { q: params.term || '', page: params.page || 1 };
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
                return peer.text || 'Select peer';
            }
        }).on('select2:select', function (event) {
            const count = Number(event.params.data.active_push_tokens_count || 0);
            const help = document.getElementById('push-token-help');
            if (help) {
                help.textContent = count > 0 ? `Push available (${count} active token${count === 1 ? '' : 's'})` : 'No active push token';
                help.className = count > 0 ? 'form-text text-success' : 'form-text text-warning';
            }
        });
    });
</script>
@endpush

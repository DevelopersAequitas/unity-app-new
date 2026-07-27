@extends('admin.layouts.app')
@section('title', 'Daily Notification Reminders')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Daily Notification Reminders</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage and edit the daily engagement notifications and schedules sent to members.</p>
        </div>
        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total Reminders: {{ $reminders->count() }}</span>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <!-- Search Filter Card -->
    <div class="p-3 rounded-lg border bs surface-2">
        <div class="max-w-md">
            <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
            <input type="text" id="tableSearch" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by feature, activity, title, body or timing...">
        </div>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Feature</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Activity</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Notification (Title & Body)</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Action / Trigger Timing</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Eligible Users</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="reminderTableBody" class="divide-y divide-gray-200/50">
                    @forelse($reminders as $reminder)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">
                                    {{ $reminder->feature }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs font-semibold t1">
                                {{ $reminder->activity }}
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <div class="font-bold t1 mb-0.5">{{ $reminder->notification_title }}</div>
                                <div class="t2 max-w-[450px] truncate" title="{{ $reminder->notification_body }}">
                                    {{ $reminder->notification_body }}
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">
                                ⏱️ <span class="font-medium t1">{{ $reminder->action_trigger_timing }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <button type="button" 
                                        class="view-eligible-users-btn border-0 bg-transparent cursor-pointer"
                                        data-id="{{ $reminder->id }}"
                                        data-activity="{{ $reminder->activity }}">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                                        👥 {{ $counts[$reminder->activity] ?? 0 }}
                                    </span>
                                </button>
                            </td>
                            <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                <div class="flex justify-end gap-1.5 items-center">
                                    <form method="POST" action="{{ route('admin.daily-notifications.send', $reminder->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to send this notification to all eligible users immediately?');">
                                        @csrf
                                        <button type="submit" class="px-2 py-0.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring">
                                            Send
                                        </button>
                                    </form>
                                    <button type="button" 
                                            class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition edit-reminder-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editReminderModal"
                                            data-id="{{ $reminder->id }}"
                                            data-feature="{{ $reminder->feature }}"
                                            data-activity="{{ $reminder->activity }}"
                                            data-notification_title="{{ $reminder->notification_title }}"
                                            data-notification_body="{{ $reminder->notification_body }}"
                                            data-action_trigger_timing="{{ $reminder->action_trigger_timing }}">
                                        Edit
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-xs t3">
                                No reminders found in database. Run the seeder to populate.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>


<!-- Edit Reminder Modal -->
<div class="modal fade" id="editReminderModal" tabindex="-1" aria-labelledby="editReminderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editReminderModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Notification Reminder
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="edit_reminder_form" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Feature -->
                        <div class="col-md-6">
                            <label for="edit_feature" class="form-label fw-semibold">Feature</label>
                            <input type="text" class="form-control @error('feature') is-invalid @enderror" id="edit_feature" name="feature" required>
                            @error('feature')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action / Trigger Timing -->
                        <div class="col-md-6">
                            <label for="edit_action_trigger_timing" class="form-label fw-semibold">Action / Trigger Timing</label>
                            <input type="text" class="form-control @error('action_trigger_timing') is-invalid @enderror" id="edit_action_trigger_timing" name="action_trigger_timing" required>
                            @error('action_trigger_timing')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Activity -->
                        <div class="col-12">
                            <label for="edit_activity" class="form-label fw-semibold">Activity</label>
                            <textarea class="form-control @error('activity') is-invalid @enderror" id="edit_activity" name="activity" rows="2" required></textarea>
                            @error('activity')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Notification Title -->
                        <div class="col-12">
                            <label for="edit_notification_title" class="form-label fw-semibold">Notification Title</label>
                            <input type="text" class="form-control @error('notification_title') is-invalid @enderror" id="edit_notification_title" name="notification_title" required>
                            @error('notification_title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Notification Body -->
                        <div class="col-12">
                            <label for="edit_notification_body" class="form-label fw-semibold">Notification Body</label>
                            <textarea class="form-control @error('notification_body') is-invalid @enderror" id="edit_notification_body" name="notification_body" rows="4" required></textarea>
                            @error('notification_body')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Eligible Users Modal -->
<div class="modal fade" id="eligibleUsersModal" tabindex="-1" aria-labelledby="eligibleUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="eligibleUsersModalLabel">
                    <i class="bi bi-people-fill me-2"></i>Eligible Users List (<span id="modalUsersCount">0</span>)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <span class="fw-semibold text-secondary">Activity: </span>
                    <span id="modalActivityName" class="text-dark"></span>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto !important;">
                    <table class="table table-hover align-middle">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Name</th>
                                <th>Company Name</th>
                                <th>City</th>
                                <th>Business Category</th>
                            </tr>
                        </thead>
                        <tbody id="eligibleUsersTableBody">
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                                    Loading eligible users...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light p-3">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Table Search Logic
        const searchInput = document.getElementById('tableSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll('#reminderTableBody tr');
                
                rows.forEach(row => {
                    // Ignore empty state row
                    if (row.querySelector('td[colspan]')) return;
                    
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }

        // Modal Data Prefill Logic
        const editButtons = document.querySelectorAll('.edit-reminder-btn');
        const editForm = document.getElementById('edit_reminder_form');
        
        editButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const feature = btn.getAttribute('data-feature');
                const activity = btn.getAttribute('data-activity');
                const title = btn.getAttribute('data-notification_title');
                const body = btn.getAttribute('data-notification_body');
                const timing = btn.getAttribute('data-action_trigger_timing');

                if (editForm) {
                    editForm.action = `/admin/daily-notifications/${id}`;
                }
                
                document.getElementById('edit_feature').value = feature;
                document.getElementById('edit_activity').value = activity;
                document.getElementById('edit_notification_title').value = title;
                document.getElementById('edit_notification_body').value = body;
                document.getElementById('edit_action_trigger_timing').value = timing;
            });
        });

        // View Eligible Users AJAX Modal Logic
        const viewUsersButtons = document.querySelectorAll('.view-eligible-users-btn');
        const eligibleUsersModal = new bootstrap.Modal(document.getElementById('eligibleUsersModal'));
        const usersTableBody = document.getElementById('eligibleUsersTableBody');
        const modalActivityName = document.getElementById('modalActivityName');
        const modalUsersCount = document.getElementById('modalUsersCount');

        viewUsersButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-id');
                const activity = btn.getAttribute('data-activity');
                
                modalActivityName.textContent = activity;
                modalUsersCount.textContent = '0';
                usersTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                            Loading eligible users...
                        </td>
                    </tr>
                `;
                
                eligibleUsersModal.show();

                fetch(`/admin/daily-notifications/${id}/eligible-users`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.users.length > 0) {
                            modalUsersCount.textContent = data.users.length;
                            let rowsHtml = '';
                            data.users.forEach(user => {
                                rowsHtml += `
                                    <tr>
                                        <td><div class="fw-semibold text-dark">${user.name}</div></td>
                                        <td>${user.company_name}</td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-secondary">${user.city}</span></td>
                                        <td>${user.business_category}</td>
                                    </tr>
                                `;
                            });
                            usersTableBody.innerHTML = rowsHtml;
                        } else if (data.success) {
                            modalUsersCount.textContent = '0';
                            usersTableBody.innerHTML = `
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        <i class="bi bi-info-circle me-1"></i>No users match this criteria currently.
                                    </td>
                                </tr>
                            `;
                        } else {
                            modalUsersCount.textContent = '0';
                            usersTableBody.innerHTML = `
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-danger">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>${data.error || 'Failed to load users.'}
                                    </td>
                                </tr>
                            `;
                        }
                    })
                    .catch(error => {
                        modalUsersCount.textContent = '0';
                        usersTableBody.innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center py-4 text-danger">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Error fetching details.
                                </td>
                            </tr>
                        `;
                    });
            });
        });
    });
</script>
@endpush
@endsection

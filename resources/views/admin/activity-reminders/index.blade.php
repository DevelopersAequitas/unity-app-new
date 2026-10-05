@extends('admin.layouts.app')
@section('title', 'Smart Inactivity Activity Reminders')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Smart Inactivity Reminders</h2>
            <p class="text-xs t3 m-0 mt-0.5">Configure inactivity thresholds, FCM push notifications, and priority routing for peer activities.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                Total Modules: {{ $settings->count() }}
            </span>
            <form method="POST" action="{{ route('admin.activity-reminders.trigger') }}" class="inline" onsubmit="return confirm('Run the smart inactivity reminder check now across all eligible users?');">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition flex items-center gap-1.5">
                    <i class="bi bi-play-fill text-sm"></i> Run Check Now
                </button>
            </form>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center justify-between">
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center justify-between">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Search Filter Card -->
    <div class="p-3 rounded-lg border bs surface-2">
        <div class="max-w-md">
            <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search Modules</label>
            <input type="text" id="tableSearch" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by activity name, title, body, or route...">
        </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Priority</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Activity Module</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Inactivity Threshold</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Push Notification Title</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Notification Body</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Deep Link Screen</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Eligible Users</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="reminderTableBody" class="divide-y divide-gray-200/50">
                    @forelse($settings as $setting)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 text-xs font-bold text-center">
                                <span class="w-6 h-6 rounded-full inline-flex items-center justify-center bg-gray-100 text-gray-800 text-[11px] font-bold">
                                    {{ $setting->priority }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs font-semibold t1">
                                <div>{{ $setting->activity_name }}</div>
                                <div class="text-[10px] text-gray-400 font-mono">{{ $setting->activity_type }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-xs text-center font-medium">
                                <span class="chip px-2 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">
                                    {{ $setting->threshold_days }} Days
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs font-medium t1">
                                {{ $setting->notification_title }}
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 max-w-xs truncate" title="{{ $setting->notification_body }}">
                                {{ $setting->notification_body }}
                            </td>
                            <td class="px-3 py-2.5 text-xs font-mono text-indigo-600">
                                {{ $setting->target_screen }}
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                @if($setting->is_enabled)
                                    <span class="chip px-2 py-0.5 text-[11px] font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Enabled</span>
                                @else
                                    <span class="chip px-2 py-0.5 text-[11px] font-semibold bg-rose-50 text-rose-700 border-rose-200">Disabled</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-blue-50 text-blue-700 border-blue-200">
                                    {{ $counts[$setting->id] ?? 0 }} Users
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                <button type="button" 
                                    class="px-2.5 py-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded transition edit-btn"
                                    data-id="{{ $setting->id }}"
                                    data-activity-name="{{ $setting->activity_name }}"
                                    data-activity-type="{{ $setting->activity_type }}"
                                    data-threshold-days="{{ $setting->threshold_days }}"
                                    data-priority="{{ $setting->priority }}"
                                    data-is-enabled="{{ $setting->is_enabled ? '1' : '0' }}"
                                    data-title="{{ $setting->notification_title }}"
                                    data-body="{{ $setting->notification_body }}"
                                    data-target-screen="{{ $setting->target_screen }}"
                                >
                                    <i class="bi bi-pencil mr-1"></i> Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-8 text-center text-xs t3">
                                No activity reminder settings found. Please run migrations.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="surface rounded-xl border bs max-w-lg w-full p-5 space-y-4 shadow-xl">
        <div class="flex justify-between items-center border-b bs pb-3">
            <h3 class="font-display font-semibold text-sm t1 m-0">Edit Inactivity Reminder Setting</h3>
            <button type="button" id="closeModalBtn" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>

        <form id="editForm" method="POST" action="">
            @csrf
            @method('PUT')

            <div class="space-y-3">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Activity Name</label>
                    <input type="text" id="modalActivityName" class="px-2.5 py-1.5 text-xs rounded border bs surface-2 t1 w-full bg-gray-50" readonly>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Inactivity Threshold (Days)</label>
                        <input type="number" name="threshold_days" id="modalThresholdDays" min="1" max="365" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" required>
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Priority (1 = Highest)</label>
                        <input type="number" name="priority" id="modalPriority" min="1" max="100" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" required>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Push Notification Title</label>
                    <input type="text" name="notification_title" id="modalTitle" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" required>
                </div>

                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Push Notification Body</label>
                    <textarea name="notification_body" id="modalBody" rows="3" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" required></textarea>
                </div>

                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Deep Link Target Screen</label>
                    <input type="text" name="target_screen" id="modalTargetScreen" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring font-mono" required>
                </div>

                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="is_enabled" id="modalIsEnabled" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t bs mt-4">
                <button type="button" id="cancelModalBtn" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                    Cancel
                </button>
                <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editModal = document.getElementById('editModal');
    const editForm = document.getElementById('editForm');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');

    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            editForm.action = `/admin/activity-reminders/${id}`;

            document.getElementById('modalActivityName').value = btn.getAttribute('data-activity-name');
            document.getElementById('modalThresholdDays').value = btn.getAttribute('data-threshold-days');
            document.getElementById('modalPriority').value = btn.getAttribute('data-priority');
            document.getElementById('modalTitle').value = btn.getAttribute('data-title');
            document.getElementById('modalBody').value = btn.getAttribute('data-body');
            document.getElementById('modalTargetScreen').value = btn.getAttribute('data-target-screen');
            document.getElementById('modalIsEnabled').value = btn.getAttribute('data-is-enabled');

            editModal.classList.remove('hidden');
        });
    });

    const closeModal = () => editModal.classList.add('hidden');
    closeModalBtn.addEventListener('click', closeModal);
    cancelModalBtn.addEventListener('click', closeModal);

    // Search filter
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            document.querySelectorAll('#reminderTableBody tr').forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }
});
</script>
@endsection

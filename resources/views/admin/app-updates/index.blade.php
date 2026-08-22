@extends('admin.layouts.app')
@section('title', 'App Updates Manager')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">App Updates Manager</h2>
            <p class="text-xs t3 m-0 mt-0.5">Configure platform updates and notify users who are running outdated application versions.</p>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif



    <!-- Config Grid (Android & iOS) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Android Config -->
        <div class="p-4 rounded-xl border bs bg-white shadow-sm space-y-4">
            <form method="POST" action="{{ route('admin.app-updates.save', 'android') }}">
                @csrf
                <div class="flex justify-between items-center pb-2 border-b">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🤖</span>
                        <h3 class="font-semibold text-sm t1 m-0">Android Config</h3>
                    </div>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ $androidConfig->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-semibold t2">Active</span>
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Latest Version</label>
                        <input type="text" name="latest_version" value="{{ $androidConfig->latest_version }}" required class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Critical Version (Forced)</label>
                        <input type="text" name="min_version" value="{{ $androidConfig->min_version }}" required class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Update Level</label>
                    <select name="update_type" required class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="optional" {{ strtolower((string)$androidConfig->update_type) === 'optional' ? 'selected' : '' }}>Optional Update (Shows banner/dismissible popup)</option>
                        <option value="force" {{ in_array(strtolower((string)$androidConfig->update_type), ['force', 'forced'], true) ? 'selected' : '' }}>Force Update (Blocks user from using app until updated)</option>
                    </select>
                </div>

                <div class="mt-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">App Store / Play Store URL</label>
                    <input type="text" readonly value="{{ $playStoreUrl }}" class="px-2.5 py-1.5 text-xs rounded border bs bg-gray-50 t3 w-full outline-none" title="Static for now">
                </div>

                <div class="mt-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Release Notes</label>
                    <textarea name="release_notes" rows="3" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Describe the release features...">{{ $androidConfig->release_notes }}</textarea>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring flex-1">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- iOS Config -->
        <div class="p-4 rounded-xl border bs bg-white shadow-sm space-y-4">
            <form method="POST" action="{{ route('admin.app-updates.save', 'ios') }}">
                @csrf
                <div class="flex justify-between items-center pb-2 border-b">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🍏</span>
                        <h3 class="font-semibold text-sm t1 m-0">iOS Config</h3>
                    </div>
                    <label class="flex items-center gap-1.5 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ $iosConfig->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-semibold t2">Active</span>
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Latest Version</label>
                        <input type="text" name="latest_version" value="{{ $iosConfig->latest_version }}" required class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                    </div>
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Critical Version (Forced)</label>
                        <input type="text" name="min_version" value="{{ $iosConfig->min_version }}" required class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Update Level</label>
                    <select name="update_type" required class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="optional" {{ strtolower((string)$iosConfig->update_type) === 'optional' ? 'selected' : '' }}>Optional Update (Shows banner/dismissible popup)</option>
                        <option value="force" {{ in_array(strtolower((string)$iosConfig->update_type), ['force', 'forced'], true) ? 'selected' : '' }}>Force Update (Blocks user from using app until updated)</option>
                    </select>
                </div>

                <div class="mt-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">App Store / Play Store URL</label>
                    <input type="text" readonly value="{{ $appStoreUrl }}" class="px-2.5 py-1.5 text-xs rounded border bs bg-gray-50 t3 w-full outline-none" title="Static for now">
                </div>

                <div class="mt-3">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Release Notes</label>
                    <textarea name="release_notes" rows="3" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Describe the release features...">{{ $iosConfig->release_notes }}</textarea>
                </div>

                <div class="flex gap-2 mt-4">
                    <button type="submit" class="px-4 py-2 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring flex-1">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Search Filter -->
    <div class="p-3 rounded-lg border bs surface-2 flex flex-wrap justify-between items-center gap-3">
        <form method="GET" action="{{ route('admin.app-updates.index') }}" class="max-w-md w-full flex gap-2">
            <input type="text" name="search" value="{{ request('search') }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by name, email, model or version...">
            <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring">
                Search
            </button>
        </form>
        <div>
            <button type="button" id="notifySelectedBtn" class="px-3 py-1.5 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring flex items-center gap-1.5">
                🔔 Notify Selected (<span id="selectedCount">0</span>)
            </button>
        </div>
    </div>

    <!-- Users Table -->
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-10">
                            <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">User Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Platform</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Installed Version</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Update Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Active (Yes/No)</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Last Sync</th>
                    </tr>
                </thead>
                <tbody id="userTableBody" class="divide-y divide-gray-200/50">
                    @forelse($userVersions as $record)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 text-xs">
                                <input type="checkbox" name="selected_users[]" value="{{ optional($record->user)->id }}" class="user-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <div class="font-bold t1">{{ optional($record->user)->display_name ?? 'Unknown' }}</div>
                                <div class="text-[10px] text-gray-500">{{ optional($record->user)->email ?? 'No email' }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-xs font-semibold t1 uppercase">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ strtolower($record->platform) === 'ios' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200' }}">
                                    {{ $record->platform }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t1">
                                <span class="font-medium">{{ $record->app_version }}</span>
                                <div class="text-[9px] text-gray-400">Device: {{ $record->device_model ?? 'N/A' }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ $record->status_class }}">
                                    {{ $record->status_text }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                @if(strtolower(optional($record->user)->status ?? 'active') === 'active')
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">
                                        Active
                                    </span>
                                @else
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs t2 text-right">
                                {{ $record->updated_at ? $record->updated_at->format('M d, Y h:i A') : 'N/A' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-xs t3">
                                No mobile records found matching filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $userVersions->appends(request()->query())->links() }}
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    const selectedCountSpan = document.getElementById('selectedCount');
    const notifySelectedBtn = document.getElementById('notifySelectedBtn');

    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
        selectedCountSpan.textContent = checkedCount;
    }

    selectAllCheckbox.addEventListener('change', function () {
        userCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateSelectedCount();
    });

    userCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            if (!this.checked) {
                selectAllCheckbox.checked = false;
            }
            updateSelectedCount();
        });
    });

    notifySelectedBtn.addEventListener('click', function () {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const userIds = Array.from(checkedBoxes).map(cb => cb.value).filter(id => id);

        if (userIds.length === 0) {
            alert('Please select at least one user with checkboxes.');
            return;
        }

        if (!confirm(`Are you sure you want to send app update push notifications to ${userIds.length} users?`)) {
            return;
        }

        notifySelectedBtn.disabled = true;
        notifySelectedBtn.textContent = 'Sending...';

        fetch("{{ route('admin.app-updates.notify-selected') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ user_ids: userIds })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                // Clear checkboxes
                userCheckboxes.forEach(cb => cb.checked = false);
                selectAllCheckbox.checked = false;
                updateSelectedCount();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong sending notifications.');
        })
        .finally(() => {
            notifySelectedBtn.disabled = false;
            notifySelectedBtn.textContent = `🔔 Notify Selected (${document.querySelectorAll('.user-checkbox:checked').length})`;
        });
    });
});
</script>
@endpush
@endsection

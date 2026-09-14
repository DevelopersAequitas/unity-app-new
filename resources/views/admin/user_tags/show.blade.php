@extends('admin.layouts.app')

@section('title', $tag->name . ' - Tagged Users Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 md:p-5 relative admin-grid-card space-y-4 w-full">
    {{-- Header & Action Buttons --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-2 border-b bs">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg {{ $tag->slug === 'team_member' ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-indigo-50 text-indigo-600 border-indigo-100' }} border">
                    <i class="bi {{ $tag->slug === 'team_member' ? 'bi-shield-check' : 'bi-tags-fill' }} text-base"></i>
                </span>
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">{{ $tag->name }}</h2>
                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-mono text-[11px] font-medium border bs">
                            {{ $tag->slug }}
                        </span>
                        @if($tag->is_active)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Active
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                Inactive
                            </span>
                        @endif
                    </div>
                    <p class="text-xs t3 m-0 mt-0.5">{{ $tag->description ?: 'Assigned users list and management.' }}</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" onclick="openAddUserModal()" class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-person-plus-fill"></i> + Add User
            </button>
            <a href="{{ route('admin.user-tags.edit', $tag) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1.5 bg-white shadow-xs">
                <i class="bi bi-pencil"></i> Edit Tag
            </a>
            <a href="{{ route('admin.user-tags.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1.5 bg-white shadow-xs">
                <i class="bi bi-arrow-left"></i> All Tags
            </a>
        </div>
    </div>

    {{-- Notification Alerts --}}
    @if(session('error'))
        <div class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2 shadow-xs">
            <i class="bi bi-exclamation-octagon-fill text-sm text-rose-600 flex-shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center gap-2 shadow-xs">
            <i class="bi bi-check-circle-fill text-sm text-emerald-600 flex-shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Special System Tag Banner --}}
    @if($tag->slug === 'team_member')
        <div class="p-4 rounded-xl bg-amber-50/80 border border-amber-200 text-xs text-amber-900 flex items-start gap-3 shadow-xs">
            <i class="bi bi-shield-lock-fill text-amber-600 text-lg flex-shrink-0"></i>
            <div>
                <strong class="font-semibold text-amber-950 block text-[13px] mb-0.5">Leaderboard Exclusion Active</strong>
                <p class="m-0 text-amber-800 leading-relaxed">
                    Users in this list are marked as internal <strong>Team Members</strong>. Their testing activity, coins, and impacts remain safely stored in the database, but their profiles are <strong>completely excluded</strong> from public mobile app leaderboards (<code>/api/v1/leaderboards/coins</code> & <code>/api/v1/leaderboards/impacts</code>).
                </p>
            </div>
        </div>
    @endif

    {{-- Search Filter Toolbar for Assigned Users --}}
    <div class="border bs rounded-xl p-3.5 surface-2">
        <form method="GET" action="{{ route('admin.user-tags.show', $tag) }}" class="flex items-center gap-2">
            <div style="position: relative; flex: 1; max-width: 28rem;">
                <i class="bi bi-search text-gray-400" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.75rem; pointer-events: none;"></i>
                <input type="text" name="q" value="{{ $search }}" class="rounded-lg border bs surface t1 text-xs outline-none focus-ring w-full" style="padding: 0.375rem 0.75rem 0.375rem 2rem;" placeholder="Search assigned users by name, email, company...">
            </div>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition bg-white shadow-xs">
                Search
            </button>
            @if($search !== '')
                <a href="{{ route('admin.user-tags.show', $tag) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline bg-white">
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Assigned Users Table --}}
    <div class="rounded-xl border bs surface overflow-hidden w-full shadow-sm">
        <div class="overflow-x-auto relative w-full">
            <table class="w-full min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell px-4 py-3 text-left font-semibold" style="width: 260px;">User Name</th>
                        <th class="th-cell px-4 py-3 text-left font-semibold" style="width: 240px;">Email & Phone</th>
                        <th class="th-cell px-4 py-3 text-left font-semibold">Company / City</th>
                        <th class="th-cell px-4 py-3 text-center font-semibold" style="width: 140px;">Coins / Impacts</th>
                        <th class="th-cell px-4 py-3 text-center font-semibold" style="width: 150px;">Assigned Date</th>
                        <th class="th-cell px-4 py-3 text-right font-semibold" style="width: 140px;">Action</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/60">
                    @forelse ($users as $user)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-4 py-3 font-medium t1 text-[13px]">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center flex-shrink-0 text-xs font-bold border border-indigo-200">
                                        {{ strtoupper(substr($user->display_name ?: $user->first_name ?: 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.users.show', $user->id) }}" class="font-semibold t1 hover:text-indigo-600 no-underline transition block">
                                            {{ $user->display_name ?: trim($user->first_name . ' ' . $user->last_name) ?: 'Unnamed User' }}
                                        </a>
                                        <span class="text-[11px] t3 font-mono text-gray-500">
                                            ID: {{ substr($user->id, 0, 8) }}...
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs t2">
                                <div class="font-medium t1">{{ $user->email }}</div>
                                @if($user->phone)
                                    <div class="text-gray-500 text-[11px]">{{ $user->phone }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs t2">
                                <div>{{ $user->company_name ?: '—' }}</div>
                                @if($user->city)
                                    <div class="text-[11px] text-gray-500">{{ $user->city }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-xs whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 font-semibold text-[11px]">
                                    🪙 {{ number_format((int) ($user->coins_balance ?? 0)) }}
                                </span>
                                <span class="ml-1 px-2 py-0.5 rounded bg-blue-50 text-blue-800 border border-blue-200 font-semibold text-[11px]">
                                    ❤️ {{ number_format((int) ($user->life_impacted_count ?? 0)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-600 whitespace-nowrap">
                                {{ $user->pivot->created_at ? $user->pivot->created_at->format('d M Y, h:i A') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.user-tags.users.remove', ['userTag' => $tag->id, 'userId' => $user->id]) }}" class="inline-block m-0" onsubmit="return confirmRemoveUser('{{ addslashes($user->display_name ?: $user->email) }}', '{{ $tag->slug }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1 rounded-lg border border-rose-200 text-xs font-semibold text-rose-600 hover:text-rose-700 bg-rose-50/60 hover:bg-rose-50 transition inline-flex items-center gap-1 shadow-xs" title="Remove tag assignment">
                                        <i class="bi bi-x-circle"></i> Remove Tag
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-10 text-xs t3">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-people text-3xl text-gray-300 mb-2"></i>
                                    <span class="font-medium text-gray-600">No users currently assigned to this tag.</span>
                                    <p class="text-[11px] text-gray-400 mt-1">Click "+ Add User" above to search and assign users.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="px-4 py-3 border-t bs bg-gray-50/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Add User Modal --}}
<div id="addUserModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border bs shadow-2xl max-w-lg w-full overflow-hidden flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-150">
        {{-- Modal Header --}}
        <div class="p-4 border-b bs flex items-center justify-between bg-gray-50/70">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs font-bold border border-indigo-100">
                    <i class="bi bi-person-plus-fill"></i>
                </span>
                <div>
                    <h3 class="font-semibold text-sm t1 m-0">Assign User to {{ $tag->name }}</h3>
                    <p class="text-[11px] t3 m-0">Search and select an existing user account.</p>
                </div>
            </div>
            <button type="button" onclick="closeAddUserModal()" class="text-gray-400 hover:text-gray-600 text-lg p-1 rounded-lg hover:bg-gray-100 transition">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        {{-- Modal Body --}}
        <div class="p-4 space-y-3 overflow-y-auto flex-1">
            {{-- Search Input --}}
            <div style="position: relative;">
                <i class="bi bi-search text-gray-400" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.75rem; pointer-events: none;"></i>
                <input
                    type="text"
                    id="userSearchInput"
                    oninput="debounceUserSearch()"
                    placeholder="Search by user name, email, phone, company..."
                    class="rounded-lg border bs surface t1 text-xs outline-none focus-ring w-full shadow-xs"
                    style="padding: 0.5rem 0.75rem 0.5rem 2rem;"
                    autocomplete="off"
                >
            </div>

            {{-- Selected User Banner --}}
            <div id="selectedUserBanner" class="hidden p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-xs text-indigo-900 flex items-center justify-between">
                <div>
                    <strong class="font-semibold block text-indigo-950" id="selectedUserName">Selected User</strong>
                    <span class="text-[11px] text-indigo-700" id="selectedUserEmail">user@example.com</span>
                </div>
                <button type="button" onclick="clearSelectedUser()" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold underline">
                    Change
                </button>
            </div>

            {{-- Search Results Container --}}
            <div id="searchResultsList" class="space-y-1.5 max-h-60 overflow-y-auto border bs rounded-xl p-2 bg-gray-50/40">
                <div class="text-center py-6 text-xs text-gray-400">
                    <i class="bi bi-search text-lg block mb-1"></i>
                    Type at least 1 character to search users...
                </div>
            </div>

            {{-- Confirmation Notice --}}
            @if($tag->slug === 'team_member')
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-[11px] text-amber-900 leading-relaxed">
                    <strong class="font-semibold block mb-0.5 text-amber-950"><i class="bi bi-info-circle-fill"></i> Leaderboard Notice:</strong>
                    Assigning this user to <strong>Team Member</strong> will immediately exclude them from public Coins and Impacts leaderboards. Their test coins and impacts will remain intact.
                </div>
            @endif
        </div>

        {{-- Modal Footer --}}
        <div class="p-4 border-t bs bg-gray-50/70 flex items-center justify-end gap-2">
            <button type="button" onclick="closeAddUserModal()" class="px-4 py-2 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition bg-white">
                Cancel
            </button>
            <form id="assignUserForm" method="POST" action="{{ route('admin.user-tags.users.assign', $tag) }}" class="m-0" onsubmit="return confirmAssignUser('{{ $tag->slug }}');">
                @csrf
                <input type="hidden" name="user_id" id="selectedUserId" value="">
                <button type="submit" id="confirmAssignBtn" disabled class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold transition focus-ring shadow-sm flex items-center gap-1">
                    <i class="bi bi-check-lg"></i> Confirm & Assign
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let searchTimeout = null;
const tagSlug = @json($tag->slug);
const searchUrl = @json(route('admin.user-tags.users.search', $tag));

function openAddUserModal() {
    document.getElementById('addUserModal').classList.remove('hidden');
    document.getElementById('userSearchInput').value = '';
    clearSelectedUser();
    performSearch('');
    setTimeout(() => document.getElementById('userSearchInput').focus(), 50);
}

function closeAddUserModal() {
    document.getElementById('addUserModal').classList.add('hidden');
}

function debounceUserSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        const query = document.getElementById('userSearchInput').value.trim();
        performSearch(query);
    }, 250);
}

function performSearch(query) {
    const list = document.getElementById('searchResultsList');
    list.innerHTML = '<div class="text-center py-4 text-xs text-gray-400"><i class="bi bi-hourglass-split animate-spin text-sm block mb-1"></i> Searching users...</div>';

    fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(users => {
        if (!users || users.length === 0) {
            list.innerHTML = '<div class="text-center py-6 text-xs text-gray-400">No matching eligible users found.</div>';
            return;
        }

        let html = '';
        users.forEach(u => {
            html += `
                <div onclick="selectUser('${u.id}', '${escapeHtml(u.name)}', '${escapeHtml(u.email)}')" class="p-2.5 rounded-lg hover:bg-indigo-50 border border-gray-100 hover:border-indigo-200 cursor-pointer transition flex items-center justify-between group bg-white shadow-xs">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center text-xs font-bold group-hover:bg-indigo-600 group-hover:text-white transition flex-shrink-0">
                            ${(u.name || 'U').charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div class="font-semibold text-xs text-gray-900 group-hover:text-indigo-950 flex items-center gap-2">
                                <span>${escapeHtml(u.name)}</span>
                                <span class="px-1.5 py-0.2 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold">🪙 ${Number(u.coins || 0).toLocaleString()}</span>
                            </div>
                            <div class="text-[11px] text-gray-500">${escapeHtml(u.email)} ${u.company ? '• ' + escapeHtml(u.company) : ''}</div>
                        </div>
                    </div>
                    <button type="button" class="px-2.5 py-1 rounded-md text-[11px] font-semibold bg-indigo-50 border border-indigo-200 text-indigo-700 group-hover:bg-indigo-600 group-hover:text-white transition shadow-xs">
                        Select
                    </button>
                </div>
            `;
        });
        list.innerHTML = html;
    })
    .catch(err => {
        list.innerHTML = '<div class="text-center py-4 text-xs text-rose-500">Failed to load users. Please try again.</div>';
    });
}

function selectUser(id, name, email) {
    document.getElementById('selectedUserId').value = id;
    document.getElementById('selectedUserName').textContent = name;
    document.getElementById('selectedUserEmail').textContent = email;
    document.getElementById('selectedUserBanner').classList.remove('hidden');
    document.getElementById('confirmAssignBtn').removeAttribute('disabled');
}

function clearSelectedUser() {
    document.getElementById('selectedUserId').value = '';
    document.getElementById('selectedUserBanner').classList.add('hidden');
    document.getElementById('confirmAssignBtn').setAttribute('disabled', 'disabled');
}

function confirmAssignUser(slug) {
    const userId = document.getElementById('selectedUserId').value;
    if (!userId) {
        alert('Please select a user first.');
        return false;
    }
    const userName = document.getElementById('selectedUserName').textContent;
    if (slug === 'team_member') {
        return confirm(`Are you sure you want to mark "${userName}" as a Team Member?\n\nTeam Members are excluded from the public Coins and Impacts leaderboards.`);
    }
    return confirm(`Are you sure you want to assign "${userName}" to this tag?`);
}

function confirmRemoveUser(userName, slug) {
    if (slug === 'team_member') {
        return confirm(`Are you sure you want to remove the Team Member tag from "${userName}"?\n\nThis user may become eligible to appear in the public leaderboards.`);
    }
    return confirm(`Are you sure you want to remove this tag from "${userName}"?`);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>
@endsection
